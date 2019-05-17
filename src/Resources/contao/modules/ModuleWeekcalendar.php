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
use Contao\Date;
use Contao\FrontendUser;
use Contao\Message;
use Contao\Module;
use Contao\Input;
use Contao\Environment;
use Contao\Controller;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingResourceTypeModel;
use Contao\StringUtil;
use Contao\Config;
use Patchwork\Utf8;
use Symfony\Component\HttpFoundation\JsonResponse;

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
    public $intSelectedDate;

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

        // Get intBackWeeks && intBackWeeks
        $this->intBackWeeks = Config::get('rbb_intBackWeeks');
        $this->intAheadWeeks = Config::get('rbb_intAheadWeeks');

        if (!isset($_SESSION['rbb']))
        {
            $_SESSION['rbb'] = array();
        }
        $strResType = (isset($_SESSION['rbb']['resType']) && $_SESSION['rbb']['resType'] > 0) ? $_SESSION['rbb']['resType'] : '';
        $strRes = (isset($_SESSION['rbb']['res']) && $_SESSION['rbb']['res'] > 0) ? $_SESSION['rbb']['res'] : '';
        $strDate = (isset($_SESSION['rbb']['date']) && $_SESSION['rbb']['date'] > 0) ? $_SESSION['rbb']['date'] : '';
        $strResType = Input::post('resType') != '' ? Input::post('resType') : $strResType;
        $strRes = Input::post('res') != '' ? Input::post('res') : $strRes;
        $strDate = Input::post('date') != '' ? Input::post('date') : $strDate;

        $this->objSelectedResourceType = ResourceBookingResourceTypeModel::findByPk($strResType);
        $this->objSelectedResource = ResourceBookingResourceModel::findByPk($strRes);
        $strDate = $this->isValidDate($strDate) ? $strDate : '';
        if ($strDate == '')
        {
            $strDate = DateHelper::getMondayOfCurrentWeek();
        }
        $this->intSelectedDate = $strDate;
        $_SESSION['rbb']['resType'] = $strResType;
        $_SESSION['rbb']['res'] = $strRes;
        $_SESSION['rbb']['date'] = $strDate;

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
        if ($this->hasError)
        {
            $this->Template->hasError = $this->hasError;
            $this->Template->errorMessages = Message::generateUnwrapped();
        }

        $this->Template->objSelectedResourceType = $this->objSelectedResourceType;
        $this->Template->objSelectedResource = $this->objSelectedResource;
        $this->Template->mondayOfThisWeek = DateHelper::getMondayOfCurrentWeek();
        $this->Template->intSelectedDate = $this->intSelectedDate;
        if ($this->objSelectedResourceType !== null)
        {
            $this->Template->showResourceSelector = true;
        }
        if ($this->objSelectedResourceType !== null && $this->objSelectedResource !== null)
        {
            $this->Template->showDateSelector = true;
            $this->Template->showGoToPrevWeekBtn = true;
            $this->Template->showGoToNextWeekBtn = true;
        }

        // Create 1 week back and 1 week ahead links
        $url = \Haste\Util\Url::removeQueryString(['date'], Environment::get('request'));
        $backTime = DateHelper::addDaysToTime(-7, $this->intSelectedDate);
        $aheadTime = DateHelper::addDaysToTime(7, $this->intSelectedDate);
        if (!$this->isValidDate($backTime))
        {
            $this->Template->disablePrevWeekBtn = true;
            $backTime = $this->intSelectedDate;
        }
        if (!$this->isValidDate($aheadTime))
        {
            $this->Template->disableNextWeekBtn = true;
            $aheadTime = $this->intSelectedDate;
        }
        $this->Template->minus1WeekUrl = \Haste\Util\Url::addQueryString('date=' . $backTime, $url);
        $this->Template->plus1WeekUrl = \Haste\Util\Url::addQueryString('date=' . $aheadTime, $url);
    }

    /**
     * @param $tstamp
     * @return bool
     */
    public function isValidDate($tstamp)
    {
        $arrWeeks = array();
        for ($i = $this->intBackWeeks; $i <= $this->intAheadWeeks; $i++)
        {
            $arrWeeks[] = strtotime('monday ' . (string)$i . ' week');
        }
        if (in_array($tstamp, $arrWeeks))
        {
            return true;
        }
        return false;
    }

}

class_alias(ModuleWeekcalendar::class, 'ModuleWeekcalendar');
