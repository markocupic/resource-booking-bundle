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
use Contao\Message;
use Contao\Messages;
use Contao\Module;
use Contao\Input;
use Contao\Environment;
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

    public $objResourceTypes;

    public $objSelectedResourceType;

    public $objResources;

    public $objSelectedResource;

    public $intSelectedDate;

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



        // Set selected date from query string
        $this->intSelectedDate = $this->getMondayOfThisWeek();
        if (Input::get('date') != '')
        {
            if ($this->isValidDate(Input::get('date')))
            {
                $this->intSelectedDate = Input::get('date');
            }
        }

        // Get resource types
        $arrResTypesIds = StringUtil::deserialize($this->resourceReservation_resourceTypes, true);
        $this->objResourceTypes = ResourceReservationResourceTypeModel::findMultipleAndPublishedByIds($arrResTypesIds);
        if ($this->objResourceTypes === null)
        {
            Message::addError('Bitte legen Sie in den Moduleinstellungen mindestens einen Resourcen-Typ fest.');
            $blnError = true;
            return parent::generate();
        }

        if (Input::get('resType') != '')
        {
            $objSelectedResourceType = ResourceReservationResourceTypeModel::findByPk(Input::get('resType'));
            if ($objSelectedResourceType === null)
            {
                Message::addError('Ungültigen Resourcen-Typ ausgewählt.');
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
                        Message::addError('Ungültige Resource ausgewählt.');
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
        $this->Template->weekSelection = $this->getWeekSelection();
        $this->Template->mondayOfThisWeek = $this->getMondayOfThisWeek();
        $this->Template->intSelectedDate = $this->intSelectedDate;
    }

    /**
     * @return array
     */
    public function getWeekSelection()
    {
        $arrWeeks = array();
        for ($i = -27; $i <= 51; $i++)
        {
            // add empty
            if ($this->getMondayOfThisWeek() == strtotime('monday ' . (string)$i . ' week'))
            {
                $arrWeeks[] = array(
                    'tstamp' => '',
                    'date'   => ''
                );
            }
            $tstampMonday = strtotime('monday ' . (string)$i . ' week');
            $strMonday = Date::parse('d.m.Y', $tstampMonday);
            $tstampSunday = strtotime($strMonday . ' + 6 days');
            $strSunday = Date::parse('d.m.Y', $tstampSunday);
            $arrWeeks[] = array(
                'tstamp'       => strtotime('monday ' . (string)$i . ' week'),
                'date'         => Date::parse('d.m.Y', $monday),
                'tstampMonday' => $tstampMonday,
                'tstampSunday' => $tstampSunday,
                'stringMonday' => $strMonday,
                'stringSunday' => $strSunday,
                'daySpan'      => $strMonday . ' - ' . $strSunday,
                'calWeek'      => Date::parse('W', $tstampMonday),
                'year'         => Date::parse('Y', $tstampMonday)
            );
        }

        return $arrWeeks;
    }

    /**
     * @return false|int
     */
    public function getMondayOfThisWeek()
    {
        return strtotime('monday this week');
    }

    /**
     * @param $tstamp
     * @return bool
     */
    public function isValidDate($tstamp)
    {
        $arrWeeks = array();
        for ($i = -27; $i <= 51; $i++)
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
