<?php

declare(strict_types=1);

/**
 * Export table module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package export_table
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/export_table
 */

namespace Markocupic\ExportTable\Export;

use Contao\Backend;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\File;
use Contao\Folder;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ExportTable
 * @package Markocupic\ExportTable\Export
 */
class ExportTable extends Backend
{

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $strTable;

    /**
     * @var array
     */
    private $arrOptions;

    /**
     * @var array
     */
    private $arrData = [];

    /**
     * ExportTable constructor.
     * @param string $projectDir
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param Connection $connection
     */
    public function __construct(string $projectDir, ContaoFramework $framework, RequestStack $requestStack, Connection $connection)
    {
        $this->projectDir = $projectDir;
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;

        $this->framework->initialize();
    }

    /**
     * @throws \Exception
     */
    public function prepareExport()
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        if (TL_MODE === 'FE' && $inputAdapter->get('action') === 'exportTable' && $inputAdapter->get('key') != '')
        {
            // Deep link export requires an id
            $token = $inputAdapter->get('key');
            $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_export_table WHERE activateDeepLinkExport=? AND deepLinkExportKey=?')->execute('1', $token);
        }
        elseif (TL_MODE === 'BE' && $inputAdapter->get('id') !== '' && $inputAdapter->get('do') === 'export_table')
        {
            // Deep link export requires an id
            $id = $inputAdapter->get('id');
            $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_export_table WHERE id=?')->execute($id);
        }
        else
        {
            throw new \Exception('You are not allowed to use this service.');
        }

        if (!$objDb->numRows)
        {
            throw new \Exception('You are not allowed to use this service.');
        }
        else
        {
            if (TL_MODE === 'FE' && !$objDb->activateDeepLinkExport)
            {
                throw new \Exception('You are not allowed to use this service.');
            }

            $strTable = (string) $objDb->export_table;
            $arrSelectedFields = $stringUtilAdapter->deserialize($objDb->fields, true);

            $filterExpression = trim((string) $objDb->filterExpression);

            if (TL_MODE === 'FE' && $objDb->activateDeepLinkExport)
            {
                // Replace {{GET::*}} with GET parameter
                if (preg_match_all('/{{GET::(.*)}}/', $filterExpression, $matches))
                {
                    foreach ($matches[0] as $k => $v)
                    {
                        if ($inputAdapter->get($matches[1][$k]))
                        {
                            $filterExpression = str_replace($matches[0][$k], $inputAdapter->get($matches[1][$k]), $filterExpression);
                        }
                    }
                }
            }
            // Sanitize $filterExpression from {{GET::*}}
            $filterExpression = preg_replace('/{{GET::(.*)}}/', '"empty-string"', $filterExpression);

            // Replace insert tags
            $filterExpression = $controllerAdapter->replaceInsertTags($filterExpression);

            $exportType = (string) $objDb->exportType;
            $arrayDelimiter = (string) $objDb->arrayDelimiter;

            $arrForbidden = [
                'delete',
                'drop',
                'update',
                'alter',
                'truncate',
                'insert',
                'create',
                'clone',
            ];
            foreach ($arrForbidden as $expr)
            {
                if (strpos(strtolower($filterExpression), $expr) !== false)
                {
                    throw new \Exception('Illegal filter expression! Do not use "' . strtoupper($expr) . '" in your filter expression.');
                }
            }

            $sortingExpression = '';
            if ($objDb->sortBy != '' && $objDb->sortByDirection != '')
            {
                $sortingExpression = $objDb->sortBy . ' ' . $objDb->sortByDirection;
            }
        }

        $options = [
            'strSorting'          => $sortingExpression,
            'exportType'          => $exportType,
            'strDelimiter'        => ';',
            'strEnclosure'        => '"',
            'arrFilter'           => $filterExpression != '' ? json_decode($filterExpression) : [],
            'strDestination'      => null,
            'arrSelectedFields'   => $arrSelectedFields,
            'useLabelForHeadline' => null,
            'arrayDelimiter'      => $arrayDelimiter,
        ];

        // Call Export class
        $this->exportTable($strTable, $options);
    }

    /**
     * @param string $strTable
     * @param array $options
     * @throws \Exception
     */
    public function exportTable(string $strTable, array $options = [])
    {
        $this->strTable = $strTable;

        // Defaults
        $preDefinedOptions = [
            'strSorting'          => 'id ASC',
            // Export Type csv or xml
            'exportType'          => 'csv',
            'strDelimiter'        => ';',
            'strEnclosure'        => '"',
            // arrFilter array(array("published=?",1),array("pid=6",1))
            'arrFilter'           => [],
            // strDestination relative to the root dir f.ex: files/mydir
            'strDestination'      => null,
            // arrSelectedFields f.ex: array('firstname', 'lastname', 'street')
            'arrSelectedFields'   => null,
            // useLabelForHeadline: can be null or en, de, fr, ...
            'useLabelForHeadline' => null,
            // arrayDelimiter f.ex: ||
            'arrayDelimiter'      => '||',
        ];

        $this->arrOptions = array_merge($preDefinedOptions, $options);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        // Load Datacontainer
        if (!is_array($GLOBALS['TL_DCA'][$this->strTable]))
        {
            $controllerAdapter->loadDataContainer($this->strTable, true);
        }

        $dca = [];
        if (is_array($GLOBALS['TL_DCA'][$this->strTable]))
        {
            $dca = $GLOBALS['TL_DCA'][$this->strTable];
        }

        // If no fields are selected, then list the whole table
        $arrSelectedFields = $this->arrOptions['arrSelectedFields'];
        if ($arrSelectedFields === null || empty($arrSelectedFields))
        {
            $arrSelectedFields = $databaseAdapter->getInstance()->getFieldNames($this->strTable);
        }

        // create headline
        if ($this->arrOptions['useLabelForHeadline'] !== null)
        {
            // Use language file
            $controllerAdapter->loadLanguageFile($this->strTable, $this->arrOptions['useLabelForHeadline']);
        }

        $arrHeadline = [];
        foreach ($arrSelectedFields as $fieldname)
        {
            $arrLang = $GLOBALS['TL_LANG'][$this->strTable][$fieldname];
            if (is_array($arrLang) && isset($arrLang[0]))
            {
                $arrHeadline[] = strlen($arrLang[0]) ? $arrLang[0] : $fieldname;
            }
            else
            {
                $arrHeadline[] = $fieldname;
            }
        }
        // Add headline to  $this->arrData[]
        $this->arrData[] = $arrHeadline;

        // Handle filter expression
        // Get filter as json encoded array [[tablename.field=? OR tablename.field=?],["valueA","valueB"]]
        $arrFilter = $this->arrOptions['arrFilter'];
        if (empty($arrFilter) || !is_array($arrFilter))
        {
            $arrFilter = [];
        }

        $filterStmt = $this->strTable . ".id>?";
        $arrValues = [0];

        if (!empty($arrFilter) && is_array($arrFilter))
        {
            if (count($arrFilter) === 2)
            {
                // Statement
                if (is_array($arrFilter[0]))
                {
                    $filterStmt .= ' AND ' . implode(' AND ', $arrFilter[0]);
                }
                else
                {
                    $filterStmt .= ' AND ' . $arrFilter[0];
                }

                // Values
                if (is_array($arrFilter[1]))
                {
                    foreach ($arrFilter[1] as $v)
                    {
                        $arrValues[] = $v;
                    }
                }
                else
                {
                    $arrValues[] = $arrFilter[1];
                }
            }
        }

        $objDb = $databaseAdapter->getInstance()->prepare("SELECT * FROM  " . $this->strTable . " WHERE " . $filterStmt . " ORDER BY " . $this->arrOptions['strSorting'])->execute($arrValues);

        while ($dataRecord = $objDb->fetchAssoc())
        {
            $arrRow = [];
            foreach ($arrSelectedFields as $field)
            {
                $value = '';

                // Handle arrays correctly
                if ($dataRecord[$field] != '')
                {
                    // Replace newlines with [NEWLINE]
                    if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['inputType'] === 'textarea')
                    {
                        $value = $dataRecord[$field];
                        $dataRecord[$field] = str_replace(PHP_EOL, '[NEWLINE]', $value);
                    }

                    if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['csv'] != '')
                    {
                        $delim = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['csv'];
                        $value = implode($delim, $stringUtilAdapter->deserialize($dataRecord[$field], true));
                    }
                    elseif ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['eval']['multiple'] === true)
                    {
                        $value = implode($this->arrOptions['arrayDelimiter'], $stringUtilAdapter->deserialize($dataRecord[$field], true));
                    }
                    else
                    {
                        $value = $dataRecord[$field];
                    }
                }

                // HOOK: add custom value
                if (isset($GLOBALS['TL_HOOKS']['exportTable']) && is_array($GLOBALS['TL_HOOKS']['exportTable']))
                {
                    foreach ($GLOBALS['TL_HOOKS']['exportTable'] as $callback)
                    {
                        $objCallback = $systemAdapter->importStatic($callback[0]);
                        $value = $objCallback->{$callback[1]}($field, $value, $this->strTable, $dataRecord, $dca, $this->arrOptions);
                    }
                }

                $arrRow[] = $value;
            }
            $this->arrData[] = $arrRow;
        }

        // xml-output
        if ($this->arrOptions['exportType'] === 'xml')
        {
            $this->exportAsXml();
        }

        // csv-output
        if ($this->arrOptions['exportType'] === 'csv')
        {
            $this->exportAsCsv();
        }

        exit;
    }

    /**
     * @return Response|void
     * @throws \Exception
     */
    protected function exportAsCsv()
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        // Convert special chars
        $arrFinal = [];
        foreach ($this->arrData as $arrRow)
        {
            $arrLine = array_map(function ($v) {
                return html_entity_decode(htmlspecialchars_decode($v));
            }, $arrRow);
            $arrFinal[] = $arrLine;
        }

        // Load the CSV document from a string
        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->setDelimiter($this->arrOptions['strDelimiter']);
        $csv->setEnclosure($this->arrOptions['strEnclosure']);

        // Insert all the records
        $csv->insertAll($arrFinal);

        // Write output to file system
        if ($this->arrOptions['strDestination'] !== null)
        {
            $target = $this->arrOptions['strDestination'] . '/' . $this->strTable . '_' . $dateAdapter->parse('Y-m-d_H-i-s') . '.csv';
            return $this->writeToFile($csv->getContent(), $target);
        }
        else
        {
            // Send file to browser
            return $this->sendToBrowser($csv->getContent(), 'csv');
        }
    }

    /**
     * @throws \Exception
     */
    protected function exportAsXml()
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        $objXml = new \XMLWriter();
        $objXml->openMemory();
        $objXml->setIndent(true);
        $objXml->setIndentString("\t");
        $objXml->startDocument('1.0', 'UTF-8');

        $objXml->startElement($this->strTable);

        foreach ($this->arrData as $row => $arrRow)
        {
            // Headline
            if ($row == 0)
            {
                continue;
            }

            // New row
            $objXml->startElement('datarecord');

            foreach ($arrRow as $i => $fieldvalue)
            {
                // New field
                $objXml->startElement($this->arrData[0][$i]);

                if (is_numeric($fieldvalue) || is_null($fieldvalue) || $fieldvalue == '')
                {
                    $objXml->text($fieldvalue);
                }
                else
                {
                    // Write CDATA
                    $objXml->writeCdata($fieldvalue);
                }

                //end field-tag
                $objXml->endElement();
            }
            // End row-tag
            $objXml->endElement();
        }
        // End table-tag
        $objXml->endElement();

        // End document
        $objXml->endDocument();

        // Write output to file system
        if ($this->arrOptions['strDestination'] != '')
        {
            $target = $this->arrOptions['strDestination'] . '/' . $this->strTable . '_' . $dateAdapter->parse('Y-m-d_H-i-s') . '.xml';
            return $this->writeToFile($objXml->outputMemory(), $target);
        }

        // Send file to browser
        return $this->sendToBrowser($objXml->outputMemory(), 'xml');
    }

    /**
     * @param string $strContent
     * @param string $target
     * @throws \Exception
     */
    protected function writeToFile(string $strContent = '', string $target): void
    {
        if (!is_dir(dirname($this->projectDir . '/' . $target)))
        {
            new Folder(dirname($target));
        }

        $objFile = new File($target);

        // Write csv into file
        $objFile->write($strContent);
        $objFile->close();
    }

    /**
     * @param string $strContent
     * @param string $fileEnding
     * @return Response
     */
    protected function sendToBrowser(string $strContent = '', string $fileEnding = 'csv'): Response
    {
        // Send file to browser
        $response = new Response($strContent);
        $response->headers->set('Content-Encoding', ' UTF-8');
        $response->headers->set('Cache-Control', 'max-age=0, no-cache, must-revalidate, proxy-revalidate');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $this->strTable . '.' . $fileEnding);
        $response->headers->set('Content-Type', 'text/' . $fileEnding . '; charset=UTF-8');
        $response->headers->set('Content-Transfer-Encoding', 'binary');

        return $response->send();
    }

}
