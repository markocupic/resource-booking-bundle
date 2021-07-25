<?php

/**
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Office365Bundle for Schule Ettiswil
 * @license    MIT
 * @see        https://github.com/markocupic/office365-bundle
 *
 */

declare(strict_types=1);

namespace Markocupic\Office365Bundle\Import;

use Contao\File;
use Contao\System;
use Contao\Database;
use Contao\Validator;
use League\Csv\Reader;
use Markocupic\Office365Bundle\Message\SessionMessage;
use Markocupic\Office365Bundle\Model\Office365MemberModel;

/**
 * Class Import
 * @package Markocupic\Office365Bundle\Import
 */
class Import
{

    /** @var SessionMessage */
    private $sessionMessage;

    /**
     * Import constructor.
     * @param SessionMessage $sessionMessage
     */
    public function __construct(SessionMessage $sessionMessage)
    {
        $this->sessionMessage = $sessionMessage;
    }

    /**
     * @param string $accountType
     * @param File $objFile
     * @param string $strDelimiter
     * @param string $strEnclosure
     * @param bool $blnTestMode
     * @throws \League\Csv\Exception
     */
    public function initImport(string $accountType, File $objFile, string $strDelimiter = ';', string $strEnclosure = '"', bool $blnTestMode = false): void
    {
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $intCountInserts = 0;
        $intCountUpdates = 0;
        $intCountRows = 0;
        $activeStudentIDS = [];

        $objCsv = Reader::createFromPath($rootDir . '/' . $objFile->path, 'r');
        $objCsv->setDelimiter($strDelimiter);
        $objCsv->setEnclosure($strEnclosure);
        $objCsv->setHeaderOffset(0);

        if ($blnTestMode === true)
        {
            $this->sessionMessage->addInfoMessage('Run script in test mode.');
        }

        if ($accountType !== 'student')
        {
            $this->sessionMessage->addErrorMessage(sprintf('Account Type "%s" is still not supported.', $accountType));
        }

        if ($accountType === 'student')
        {
            $results = $objCsv->getRecords();

            foreach ($results as $row)
            {
                // Remove whitespaces
                $row = array_map('trim', $row);

                if ($row['accountType'] !== 'student')
                {
                    continue;
                }

                if (!is_numeric($row['studentId']))
                {
                    $this->sessionMessage->addErrorMessage(sprintf('Invalid student id found for "%s %s [%s]"!', $row['firstname'], $row['lastname'], $row['email']));
                    continue;
                }

                $intCountRows++;

                $objOffice365 = Office365MemberModel::findOneByStudentId($row['studentId']);
                if ($objOffice365 !== null)
                {
                    // Update record, if modified

                    $activeStudentIDS[] = $row['studentId'];

                    $arrFields = ['teacherAcronym', 'firstname', 'lastname', 'ahv', 'notice'];
                    foreach ($arrFields as $field)
                    {
                        if (!empty($row[$field]))
                        {
                            $objOffice365->$field = $row[$field];
                        }
                    }

                    $objOffice365->name = $objOffice365->firstname . ' ' . $objOffice365->lastname;

                    // Do not overwrite initialPassword!!!!!
                    if ($objOffice365->initialPassword == '')
                    {
                        $objOffice365->initialPassword = $row['initialPassword'];
                    }

                    // Do not overwrite email!!!!!
                    if ($objOffice365->email == '')
                    {
                        $objOffice365->email = $row['email'];
                    }

                    if ($objOffice365->isModified())
                    {
                        $intCountUpdates++;
                        $this->sessionMessage->addInfoMessage(sprintf('Update student "%s %s [%s]"', $objOffice365->firstname, $objOffice365->lastname, $objOffice365->email));

                        if ($blnTestMode === false)
                        {
                            $objOffice365->tstamp = time();
                            $objOffice365->save();
                        }
                    }

                    if ($blnTestMode)
                    {
                        $objOffice365->refresh();
                    }
                }
                else
                {
                    // Insert new record
                    $intCountInserts++;

                    $objOffice365 = new Office365MemberModel();
                    $activeStudentIDS[] = $row['studentId'];
                    $objOffice365->accountType = $row['accountType'];
                    $objOffice365->studentId = $row['studentId'];
                    $objOffice365->teacherAcronym = $row['teacherAcronym'];
                    $objOffice365->firstname = $row['firstname'];
                    $objOffice365->lastname = $row['lastname'];
                    $objOffice365->name = $objOffice365->firstname . ' ' . $objOffice365->lastname;
                    $objOffice365->email = $row['email'];
                    $objOffice365->ahv = $row['ahv'];
                    $objOffice365->notice = $row['notice'];

                    $objOffice365->dateAdded = time();
                    $objOffice365->tstamp = time();

                    if ($row['email'] == '')
                    {
                        $fn = $this->sanitizeName($row['firstname']);
                        $ln = $this->sanitizeName($row['lastname']);
                        $row['email'] = sprintf('%s_%s@stud.schule-ettiswil.ch', $fn, $ln);
                        $objOffice365->email = $row['email'];
                    }

                    $this->sessionMessage->addInfoMessage(sprintf('Add new student "%s %s [%s]. Check data(f.ex. email address)!!!!"', $objOffice365->firstname, $objOffice365->lastname, $objOffice365->email));

                    if ($blnTestMode === false)
                    {
                        $objOffice365->save();
                    }
                    else
                    {
                        $objOffice365->delete();
                    }
                }
            }

            // Alert, count and disable no more active students
            $intCountDeactivatedStudents = 0;
            if (!empty($activeStudentIDS))
            {
                $objDisabledStudents = Database::getInstance()->prepare('SELECT * FROM tl_office365_member WHERE accountType=? AND tl_office365_member.studentId NOT IN(' . implode(',', $activeStudentIDS) . ')')->execute($accountType);

                // Count disabled students
                $intCountDeactivatedStudents = $objDisabledStudents->numRows;

                while ($objDisabledStudents->next())
                {
                    if ($blnTestMode === false)
                    {
                        // Disable deactivated student
                        Database::getInstance()->prepare('UPDATE tl_office365_member SET disable="1" WHERE id=?')->execute($objDisabledStudents->id);
                    }

                    $this->sessionMessage->addInfoMessage(
                        sprintf(
                            'Deactivate student "%s %s"',
                            $objDisabledStudents->firstname,
                            $objDisabledStudents->lastname
                        )
                    );
                }
            }

            // Check for uniqueness and valid email address
            $objUser = Database::getInstance()->execute('SELECT * FROM tl_office365_member ORDER BY email');
            while ($objUser->next())
            {
                if ($objUser->email != '')
                {
                    if (!Database::getInstance()->isUniqueValue('tl_office365_member', 'email', $objUser->email, $objUser->id))
                    {
                        $this->sessionMessage->addErrorMessage(
                            sprintf(
                                'Email address "%s" is not unique!',
                                $objUser->email
                            )
                        );
                    }

                    if (!Validator::isEmail($objUser->email))
                    {
                        $this->sessionMessage->addErrorMessage(
                            sprintf(
                                'Invalid email address "%s"!',
                                $objUser->email
                            )
                        );
                    }
                }

                if ($objUser->studentId != '0')
                {
                    if (!Database::getInstance()->isUniqueValue('tl_office365_member', 'studentId', $objUser->studentId, $objUser->id))
                    {
                        $this->sessionMessage->addErrorMessage(
                            sprintf(
                                'studentId "%s" for "%s %s" is not unique!',
                                $objUser->studentId,
                                $objUser->firstname,
                                $objUser->lastname
                            )
                        );
                    }
                }
            }

            // Add summary
            $this->sessionMessage->addInfoMessage(
                sprintf(
                    'Terminated import process. Traversed %s datarecords. %s inserts and %s updates. Deactivated students: %s',
                    $intCountRows,
                    $intCountInserts,
                    $intCountUpdates,
                    $intCountDeactivatedStudents
                )
            );
        }
    }

    /**
     * @param string $strName
     * @return string
     */
    private function sanitizeName(string $strName = '')
    {
        $strName = trim($strName);

        // Remove control characters
        $strName = preg_replace('/[[:cntrl:]]/', '', $strName);

        if ($strName === null)
        {
            throw new \InvalidArgumentException('The file name could not be sanitized');
        }

        $arrRep = [
            'Ö' => 'OE',
            'Ä' => 'AE',
            'Ü' => 'UE',
            'É' => 'E',
            'À' => 'A',
            'ö' => 'oe',
            'ä' => 'oe',
            'ü' => 'oe',
            'é' => 'oe',
            'à' => 'oe',
            'ç' => 'c',
            '´' => '',
            '`' => '',
        ];

        foreach ($arrRep as $k => $v)
        {
            $strName = str_replace($k, $v, $strName);
        }

        $strName = strtolower($strName);

        return trim($strName);
    }

}
