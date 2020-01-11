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
     * @var FrontendUser
     */
    public $objUser;

    /**
     * @var ResourceBookingResourceTypeModel
     */
    public $objSelectedResourceType;

    /**
     * @var ResourceBookingResourceModel
     */
    public $objSelectedResource;

    /**
     * @var int
     */
    public $activeWeekTstamp;

    /**
     * @var int
     */
    public $intBackWeeks;

    /**
     * @var int
     */
    public $intAheadWeeks;

    /**
     * @var int
     */
    public $tstampFirstPossibleWeek;

    /**
     * @var int
     */
    public $tstampLastPossibleWeek;

    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
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

        // Remove query params from url if user has not logged in
        // and...
        // Return empty string if user has not logged in/**/
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

        // Catch resource type from session
        $intResType = (isset($_SESSION['rbb']['resType']) && $_SESSION['rbb']['resType'] > 0) ? $_SESSION['rbb']['resType'] : null;

        // Catch resource from session
        $intRes = (isset($_SESSION['rbb']['res']) && $_SESSION['rbb']['res'] > 0) ? $_SESSION['rbb']['res'] : null;

        // Catch date from session
        $intTstampDate = (isset($_SESSION['rbb']['date'])) ? $_SESSION['rbb']['date'] : DateHelper::getMondayOfCurrentWeek();

        $intResType = isset($_POST['resType']) ? (int)Input::post('resType') : $intResType;
        if (empty($intResType))
        {
            // Set $intResType to 0,
            // if there is no valid resType found neither in the session nor in the post
            $intResType = 0;
        }

        $intRes = isset($_POST['res']) ? (int)Input::post('res') : $intRes;
        if (empty($intRes))
        {
            // Set $intRes to 0,
            // if there is no valid res found neither in the session nor in the post
            $intRes = 0;
        }

        $this->objSelectedResourceType = ResourceBookingResourceTypeModel::findPublishedByPk($intResType);
        $this->objSelectedResource = ResourceBookingResourceModel::findPublishedByPkAndPid($intRes, $intResType);

        // Get intBackWeeks && intBackWeeks
        $this->intBackWeeks = (int)Config::get('rbb_intBackWeeks');
        $this->intAheadWeeks = (int)Config::get('rbb_intAheadWeeks');

        // Get first and last possible week tstamp
        $this->tstampFirstPossibleWeek = DateHelper::addWeeksToTime($this->intBackWeeks, DateHelper::getMondayOfCurrentWeek());
        $this->tstampLastPossibleWeek = DateHelper::addWeeksToTime($this->intAheadWeeks, DateHelper::getMondayOfCurrentWeek());

        // Get active week timestamp
        $intTstampDate = isset($_POST['date']) ? (int)Input::post('date') : $intTstampDate;
        $intTstampDate = DateHelper::isValidDate($intTstampDate) ? $intTstampDate : DateHelper::getMondayOfCurrentWeek();

        if ($intTstampDate < $this->tstampFirstPossibleWeek)
        {
            $intTstampDate = $this->tstampFirstPossibleWeek;
        }

        if ($intTstampDate > $this->tstampLastPossibleWeek)
        {
            $intTstampDate = $this->tstampLastPossibleWeek;
        }

        $this->activeWeekTstamp = $intTstampDate;

        // Store data into the session
        $_SESSION['rbb']['resType'] = (int)$intResType;
        $_SESSION['rbb']['res'] = (int)$intRes;
        $_SESSION['rbb']['date'] = (int)$intTstampDate;

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
