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

namespace Markocupic\Office365Bundle\Email;

use Contao\Config;
use Contao\Email;
use Contao\File;
use Markocupic\Office365Bundle\Model\Office365MemberModel;

/**
 * Class SendPassword
 * @package Markocupic\Office365Bundle\Email
 */
class SendPassword
{

    /**
     * SessionMessage constructor.
     */
    public function __construct()
    {

    }

    /**
     * @param string $strMessage
     */
    public function sendCredentials(Office365MemberModel $model): void
    {
        if ($model->emailSent)
        {
            return;
        }

        // Activate sending emails in the contao settings
        if(!Config::get('allowSendingEmailInTheOffice365BackendModule'))
        {
            return;
        }

        $model->emailSent = '1';
        $model->save();
        $objFile = new File('vendor/markocupic/office365-bundle/src/Resources/send_password_email.txt');
        $content = $objFile->getContent();
        //$objFile->close();

        $content = str_replace('#email#', $model->email, $content);
        $content = str_replace('#firstname#', $model->firstname, $content);
        $content = str_replace('#initialPassword#', $model->initialPassword, $content);

        $objEmail = new Email();
        $objEmail->text = $content;
        $objEmail->subject = 'Dein Zugang für die Schulwebseite/Ersatz für educanet2';
        $objEmail->from = 'marko.cupic@schule-ettiswil.ch';
        $objEmail->fromName = 'Marko Cupic';
        $objEmail->attachFile(TL_ROOT . '/vendor/markocupic/office365-bundle/src/Resources/Login_Schulwebseite_und_Raumreservation.pdf');
        $objEmail->sendTo($model->email);
    }

}
