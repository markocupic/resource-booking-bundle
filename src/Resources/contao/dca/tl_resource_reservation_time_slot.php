<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

$GLOBALS['TL_DCA']['tl_resource_reservation_time_slot'] = array
(

    // Config
    'config'   => array
    (
        'dataContainer'    => 'Table',
        'ptable'           => 'tl_resource_reservation_time_slot_type',
        'enableVersioning' => true,
        'sql'              => array
        (
            'keys' => array
            (
                'id'  => 'primary',
                'pid' => 'index'
            )
        )
    ),

    // List
    'list'     => array
    (
        'sorting'           => array
        (
            'mode'                  => 4,
            'fields'                => array('sorting'),
            'panelLayout'           => 'filter;search,limit',
            'headerFields'          => array('title'),
            'child_record_callback' => array('tl_resource_reservation_time_slot', 'childRecordCallback')
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations'        => array
        (
            'edit'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.svg'
            ),
            'copy'   => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['copy'],
                'href'       => 'act=paste&amp;mode=copy',
                'icon'       => 'copy.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ),
            'cut'    => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['cut'],
                'href'       => 'act=paste&amp;mode=cut',
                'icon'       => 'cut.svg',
                'attributes' => 'onclick="Backend.getScrollOffset()"'
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            'toggle' => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['toggle'],
                'icon'            => 'visible.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => array('tl_resource_reservation_time_slot', 'toggleIcon'),
                'showInHeader'    => true
            ),
            'show'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg'
            )
        )
    ),

    // Palettes
    'palettes' => array
    (
        'default' => '{title_legend},title,description;{time_legend},startTime,endTime',
    ),

    // Fields
    'fields'   => array
    (
        'id'          => array(
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid'         => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'tstamp'      => array(
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'sorting'     => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'"
        ),
        'title'       => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['title'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''"
        ),
        'published'   => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['published'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'filter'    => true,
            'flag'      => 2,
            'inputType' => 'checkbox',
            'eval'      => array('doNotCopy' => true, 'tl_class' => 'clr'),
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'description' => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_resource_reservation_time_slot']['description'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'textarea',
            'eval'      => array('tl_class' => 'clr'),
            'sql'       => "mediumtext NULL"
        ),
        'startTime'   => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events']['startTime'],
            'default'       => time(),
            'exclude'       => true,
            'filter'        => true,
            'sorting'       => true,
            'flag'          => 8,
            'inputType'     => 'text',
            'eval'          => array('rgxp' => 'time', 'mandatory' => true, 'tl_class' => 'w50'),
            'load_callback' => array
            (
                array('tl_resource_reservation_time_slot', 'loadTime')
            ),
            'sql'           => "int(10) NULL"
        ),
        'endTime'     => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_calendar_events']['endTime'],
            'default'       => time(),
            'exclude'       => true,
            'inputType'     => 'text',
            'eval'          => array('rgxp' => 'time', 'tl_class' => 'w50'),
            'load_callback' => array
            (
                array('tl_resource_reservation_time_slot', 'loadEndTime')
            ),
            'save_callback' => array
            (
                array('tl_resource_reservation_time_slot', 'setEmptyEndTime')
            ),
            'sql'           => "int(10) NULL"
        ),
    )
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_resource_reservation_time_slot extends Contao\Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('Contao\BackendUser', 'User');
    }

    /**
     * @param $row
     * @return string
     */
    public function childRecordCallback($row)
    {
        return sprintf('<div class="tl_content_left"><span style="color:#999;padding-left:3px">' . $row['title'] . '</span> %s-%s</div>', Contao\Date::parse('H:i', $row['startTime']), Contao\Date::parse('H:i',$row['endTime']));
    }

    /**
     * Return the "toggle visibility" button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (\strlen(Contao\Input::get('tid')))
        {
            $this->toggleVisibility(Contao\Input::get('tid'), (Contao\Input::get('state') == 1), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . ($row['published'] ? '' : 1);

        if (!$row['published'])
        {
            $icon = 'invisible.svg';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . Contao\StringUtil::specialchars($title) . '"' . $attributes . '>' . Contao\Image::getHtml($icon, $label, 'data-state="' . ($row['published'] ? 1 : 0) . '"') . '</a> ';
    }

    /**
     * Disable/enable a user group
     *
     * @param integer $intId
     * @param boolean $blnVisible
     * @param Contao\DataContainer $dc
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, Contao\DataContainer $dc = null)
    {
        // Set the ID and action
        Contao\Input::setGet('id', $intId);
        Contao\Input::setGet('act', 'toggle');

        if ($dc)
        {
            $dc->id = $intId; // see #8043
        }

        // Trigger the onload_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_resource_reservation_time_slot']['config']['onload_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_resource_reservation_time_slot']['config']['onload_callback'] as $callback)
            {
                if (\is_array($callback))
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                }
                elseif (\is_callable($callback))
                {
                    $callback($dc);
                }
            }
        }

        // Set the current record
        if ($dc)
        {
            $objRow = $this->Database->prepare("SELECT * FROM tl_resource_reservation_time_slot WHERE id=?")
                ->limit(1)
                ->execute($intId);

            if ($objRow->numRows)
            {
                $dc->activeRecord = $objRow;
            }
        }

        $objVersions = new Contao\Versions('tl_resource_reservation_time_slot', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_resource_reservation_time_slot']['fields']['published']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_resource_reservation_time_slot']['fields']['published']['save_callback'] as $callback)
            {
                if (\is_array($callback))
                {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, $dc);
                }
                elseif (\is_callable($callback))
                {
                    $blnVisible = $callback($blnVisible, $dc);
                }
            }
        }

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_resource_reservation_time_slot SET tstamp=$time, published='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
            ->execute($intId);

        if ($dc)
        {
            $dc->activeRecord->tstamp = $time;
            $dc->activeRecord->published = ($blnVisible ? '1' : '');
        }

        // Trigger the onsubmit_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_resource_reservation_time_slot']['config']['onsubmit_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_resource_reservation_time_slot']['config']['onsubmit_callback'] as $callback)
            {
                if (\is_array($callback))
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                }
                elseif (\is_callable($callback))
                {
                    $callback($dc);
                }
            }
        }

        $objVersions->create();
    }

    /**
     * Set the timestamp to 1970-01-01 (see #26)
     *
     * @param integer $value
     *
     * @return integer
     */
    public function loadTime($value)
    {
        return strtotime('1970-01-01 ' . date('H:i:s', $value));
    }

    /**
     * Set the end time to an empty string (see #23)
     *
     * @param integer $value
     * @param Contao\DataContainer $dc
     *
     * @return integer
     */
    public function loadEndTime($value, Contao\DataContainer $dc)
    {
        $return = strtotime('1970-01-01 ' . date('H:i:s', $value));

        // Return an empty string if the start time is the same as the end time (see #23)
        if ($dc->activeRecord && $return == $dc->activeRecord->startTime)
        {
            return '';
        }

        // Return an empty string if no time has been set yet
        if ($dc->activeRecord && $return - $dc->activeRecord->startTime == 86399)
        {
            return '';
        }

        return strtotime('1970-01-01 ' . date('H:i:s', $value));
    }

    /**
     * Automatically set the end time if not set
     *
     * @param mixed $varValue
     * @param Contao\DataContainer $dc
     *
     * @return string
     */
    public function setEmptyEndTime($varValue, Contao\DataContainer $dc)
    {
        if ($varValue === null)
        {
            $varValue = $dc->activeRecord->startTime;
        }

        return $varValue;
    }

}
