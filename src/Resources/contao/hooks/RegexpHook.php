<?php

/**
 * Chronometry Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package chronometry-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/chronometry-bundle
 */

namespace Markocupic\ResourceReservationBundle;

use Contao\Validator;
use Contao\Widget;

/**
 * Class RegexpHook
 * @package Markocupic\ResourceReservationBundle
 */
class RegexpHook
{

    /**
     * @param $strRegexp
     * @param $varValue
     * @param Widget $objWidget
     * @return bool
     */
    public function customRegexp($strRegexp, $varValue, Widget $objWidget)
    {
        if ($strRegexp === 'timeslottime')
        {
            if (!Validator::isTime($varValue))
            {
                $objWidget->addError($GLOBALS['TL_LANG']['MSC']['timeslottime']);
            }

            return true;
        }

        return false;
    }
}
