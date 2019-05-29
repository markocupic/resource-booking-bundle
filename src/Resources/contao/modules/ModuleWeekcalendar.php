<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\BackendTemplate;
use Contao\FrontendUser;
use Contao\Module;
use Contao\Input;
use Contao\Environment;
use Contao\Controller;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingResourceTypeModel;
use Contao\Config;
use Patchwork\Utf8;

/**
 * Class ModuleWeekcalendar
 * @package Markocupic\ResourceBookingBundle
 */
class ModuleWeekcalendar extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_resource_booking_weekcalendar';

    /**
     * @var
     */
    public $objUser;

    /**
     * @var
     */
    public $objResourceTypes;

    /**
     * @var
     */
    public $objSelectedResourceType;

    /**
     * @var
     */
    public $objResources;

    /**
     * @var
     */
    public $objSelectedResource;

    /**
     * @var
     */
    public $activeWeekTstamp;

    /**
     * @var
     */
    public $intBackWeeks;

    /**
     * @var
     */
    public $intAheadWeeks;

    /**
     * @var
     */
    public $tstampFirstPossibleWeek;

    /**
     * @var
     */
    public $tstampLastPossibleWeek;

    /**
     * @var
     */
    public $hasError;

    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        session_start();

        if (TL_MODE == 'BE')
        {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['resourceBookingWeekCalendar'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        // Remove query params from url if user is not logged in
        // and...
        // Return empty string if user is not logged in/**/
        if (!FE_USER_LOGGED_IN)
        {
            if (Input::get('date') || Input::get('resType') || Input::get('res'))
            {
                $url = \Haste\Util\Url::removeQueryString(['date', 'resType', 'res'], Environment::get('request'));
                Controller::redirect($url);
            }
            return '';
        }

        // Get the fe-user object
        $this->objUser = FrontendUser::getInstance();

        if (!isset($_SESSION['rbb']))
        {
            $_SESSION['rbb'] = array();
        }

        $strResType = (isset($_SESSION['rbb']['resType']) && $_SESSION['rbb']['resType'] > 0) ? $_SESSION['rbb']['resType'] : '';
        $strRes = (isset($_SESSION['rbb']['res']) && $_SESSION['rbb']['res'] > 0) ? $_SESSION['rbb']['res'] : '';
        $strDate = (isset($_SESSION['rbb']['date']) && $_SESSION['rbb']['date'] > 0) ? $_SESSION['rbb']['date'] : '';
        $strResType = Input::post('resType') != '' ? Input::post('resType') : $strResType;

        $strResType = isset($_POST['resType']) ? Input::post('resType') : $strResType;
        if (!$strResType > 0)
        {
            $strResType = 0;
        }
        $strRes = isset($_POST['res']) ? Input::post('res') : $strRes;
        if (!$strRes > 0)
        {
            $strRes = 0;
        }

        $this->objSelectedResourceType = ResourceBookingResourceTypeModel::findPublishedByPk($strResType);
        $this->objSelectedResource = ResourceBookingResourceModel::findPublishedByPkAndPid($strRes,$strResType);

        // Date settings
        // Get intBackWeeks && intBackWeeks
        $this->intBackWeeks = Config::get('rbb_intBackWeeks');
        $this->intAheadWeeks = Config::get('rbb_intAheadWeeks');

        // Get first ans last possible week tstamp
        $this->tstampFirstPossibleWeek = DateHelper::addWeeksToTime($this->intBackWeeks, DateHelper::getMondayOfCurrentWeek());
        $this->tstampLastPossibleWeek = DateHelper::addWeeksToTime($this->intAheadWeeks, DateHelper::getMondayOfCurrentWeek());

        $strDate = isset($_POST['date']) ? Input::post('date') : $strDate;
        $strDate = DateHelper::isValidDate($strDate) ? $strDate : '';
        if (!$strDate > 0)
        {
            $strDate = DateHelper::getMondayOfCurrentWeek();
        }
        if ($strDate < $this->tstampFirstPossibleWeek)
        {
            $strDate = $this->tstampFirstPossibleWeek;
        }
        if ($strDate > $this->tstampLastPossibleWeek)
        {
            $strDate = $this->tstampLastPossibleWeek;
        }

        $this->activeWeekTstamp = $strDate;
        $_SESSION['rbb']['resType'] = (integer)$strResType;
        $_SESSION['rbb']['res'] = (integer)$strRes;
        $_SESSION['rbb']['date'] = (integer)$strDate;

        // Handle ajax requests
        if (Environment::get('isAjaxRequest') && Input::post('action') != '')
        {
            $action = Input::post('action');
            $objXhr = new AjaxHandler();
            if (is_callable(array($objXhr, $action)))
            {
                $objXhr->{$action}($this);
            }
            exit;
        }

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        // Let's vue.js do the rest ;-)
    }

}

class_alias(ModuleWeekcalendar::class, 'ModuleWeekcalendar');
