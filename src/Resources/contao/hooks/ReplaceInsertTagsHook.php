<?php

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Contao\Controller;

/**
 * Class ReplaceInsertTagsHook
 * @package Markocupic\ResourceBookingBundle
 */
class ReplaceInsertTagsHook
{

    /**
     * @param $strTag
     * @return bool
     */
    public function replaceInsertTags($strTag)
    {
        Controller::loadLanguageFile('default');
        if (strpos($strTag, 'rbb_lang::') !== false)
        {
            $arrChunk = explode('::', $strTag);
            if (isset($arrChunk[1]))
            {
                if (isset($GLOBALS['TL_LANG']['RBB'][$arrChunk[1]]))
                {
                    return $GLOBALS['TL_LANG']['RBB'][$arrChunk[1]];
                }
                // Search in the default lang file
                if (isset($GLOBALS['TL_LANG']['MSC'][$arrChunk[1]]))
                {
                    return $GLOBALS['TL_LANG']['MSC'][$arrChunk[1]];
                }
            }
        }

        return false;
    }
}
