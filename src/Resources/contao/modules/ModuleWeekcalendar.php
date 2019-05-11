<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Markocupic\ResourceReservationBundle;

use Contao\BackendTemplate;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Message;
use Contao\Messages;
use Contao\Module;
use Contao\Input;
use Contao\Environment;
use Contao\Controller;
use Contao\ResourceReservationResourceModel;
use Contao\ResourceReservationResourceTypeModel;
use Contao\StringUtil;
use Patchwork\Utf8;

/**
 * Class ModuleWeekcalendar
 * @package Markocupic\ResourceReservationBundle
 */
class ModuleWeekcalendar extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_resource_reservation_weekcalendar';

    public $objUser;

    public $objResourceTypes;

    public $objSelectedResourceType;

    public $objResources;

    public $objSelectedResource;

    public $intSelectedDate;

    public $intBackWeeks = -27;

    public $intAheadWeeks = 51;

    public $hasError;

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
            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['resourceReservationWeekCalendar'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        if (!FE_USER_LOGGED_IN)
        {
            return '';
        }

        $this->objUser = FrontendUser::getInstance();

        // Set current week
        if (Input::get('date') == '')
        {
            $url = \Haste\Util\Url::addQueryString('date=' . DateHelper::getMondayOfCurrentWeek());
            Controller::redirect($url);
        }
        if ($this->isValidDate(Input::get('date')))
        {
            $this->intSelectedDate = Input::get('date');
        }

        // Get resource types
        $arrResTypesIds = StringUtil::deserialize($this->resourceReservation_resourceTypes, true);
        $this->objResourceTypes = ResourceReservationResourceTypeModel::findMultipleAndPublishedByIds($arrResTypesIds);
        if ($this->objResourceTypes === null)
        {
            Message::addError('Bitte legen Sie in den Moduleinstellungen mindestens einen Resourcen-Typ fest.');
            $this->hasError = true;
            return parent::generate();
        }

        // Send error message
        if (Input::get('resType') == '' || Input::get('res') == '')
        {
            Message::addInfo($GLOBALS['TL_LANG']['MSG']['selectResourcePlease']);
            $this->hasError = true;
        }

        if (Input::get('resType') != '')
        {
            $objSelectedResourceType = ResourceReservationResourceTypeModel::findByPk(Input::get('resType'));
            if ($objSelectedResourceType === null)
            {
                Message::addError($GLOBALS['TL_LANG']['MSG']['selectValidResourcePlease']);
                $this->hasError = true;
            }
            else
            {
                // Set slected resource type
                $this->objSelectedResourceType = $objSelectedResourceType;

                // Get all resources of the selected resource type
                $this->objResources = ResourceReservationResourceModel::findPublishedByPid($this->objSelectedResourceType->id);
                if (Input::get('res') != '')
                {
                    $objSelectedResource = ResourceReservationResourceModel::findByPk(Input::get('res'));
                    if ($objSelectedResource === null)
                    {
                        Message::addError($GLOBALS['TL_LANG']['MSG']['selectValidResourcePlease']);
                        $this->hasError = true;
                    }
                    else
                    {
                        // Set selected resource
                        $this->objSelectedResource = $objSelectedResource;
                    }
                }
            }
        }

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

        $this->Template->objResourceTypes = $this->objResourceTypes;
        $this->Template->objSelectedResourceType = $this->objSelectedResourceType;
        $this->Template->objResources = $this->objResources;
        $this->Template->objSelectedResource = $this->objSelectedResource;
        $this->Template->weekSelection = $this->getWeekSelection($this->intBackWeeks, $this->intAheadWeeks, true);
        $kwSelectedDate = (int)Date::parse('W', $this->intSelectedDate, true);
        $kwNow = (int)Date::parse('W');
        $this->Template->bookingRepeats = $this->getWeekSelection($kwSelectedDate - $kwNow - 1, $this->intAheadWeeks, false);
        $this->Template->mondayOfThisWeek = DateHelper::getMondayOfCurrentWeek();
        $this->Template->intSelectedDate = $this->intSelectedDate;

        // Create 1 week back and 1 week ahead links
        $url = \Haste\Util\Url::removeQueryString(['date'], Environment::get('request'));
        $backTime = DateHelper::addDaysToTime(-7, $this->intSelectedDate);
        $aheadTime = DateHelper::addDaysToTime(7, $this->intSelectedDate);
        if (!$this->isValidDate($backTime))
        {
            $this->Template->disableMinus1WeekBtn = true;
            $backTime = $this->intSelectedDate;
        }
        if (!$this->isValidDate($aheadTime))
        {
            $this->Template->disablePlus1WeekBtn = true;
            $aheadTime = $this->intSelectedDate;
        }
        $this->Template->minus1WeekUrl = \Haste\Util\Url::addQueryString('date=' . $backTime, $url);
        $this->Template->plus1WeekUrl = \Haste\Util\Url::addQueryString('date=' . $aheadTime, $url);
    }

    /**
     * @param $start
     * @param $end
     * @param bool $injectEmptyLine
     * @return array
     */
    public function getWeekSelection($start, $end, $injectEmptyLine = false)
    {
        $arrWeeks = array();
        for ($i = $start; $i <= $end; $i++)
        {
            // add empty
            if ($injectEmptyLine && DateHelper::getMondayOfCurrentWeek() == strtotime('monday ' . (string)$i . ' week'))
            {
                $arrWeeks[] = array(
                    'tstamp'     => '',
                    'date'       => '',
                    'optionText' => '-------------'
                );
            }
            $tstampMonday = strtotime('monday ' . (string)$i . ' week');
            $dateMonday = Date::parse('d.m.Y', $tstampMonday);
            $tstampSunday = strtotime($dateMonday . ' + 6 days');
            $dateSunday = Date::parse('d.m.Y', $tstampSunday);
            $calWeek = Date::parse('W', $tstampMonday);
            $yearMonday = Date::parse('Y', $tstampMonday);
            $arrWeeks[] = array(
                'tstamp'       => strtotime('monday ' . (string)$i . ' week'),
                'date'         => Date::parse('d.m.Y', $monday),
                'tstampMonday' => $tstampMonday,
                'tstampSunday' => $tstampSunday,
                'stringMonday' => $dateMonday,
                'stringSunday' => $dateSunday,
                'daySpan'      => $dateMonday . ' - ' . $dateSunday,
                'calWeek'      => $calWeek,
                'year'         => $yearMonday,
                'optionText'   => sprintf($GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'], $calWeek, $yearMonday, $dateMonday, $dateSunday)
            );
        }

        return $arrWeeks;
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
