<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Listener\ContaoHooks;

use Contao\Controller;

/**
 * Class ReplaceInsertTags.
 */
class ReplaceInsertTags
{
    /**
     * @return bool
     */
    public function onReplaceInsertTags(string $strTag)
    {
        Controller::loadLanguageFile('default');

        if (false !== strpos($strTag, 'rbb_lang::')) {
            $arrChunk = explode('::', $strTag);

            if (isset($arrChunk[1])) {
                if (isset($GLOBALS['TL_LANG']['RBB'][$arrChunk[1]])) {
                    return $GLOBALS['TL_LANG']['RBB'][$arrChunk[1]];
                }
                // Search in the default lang file
                if (isset($GLOBALS['TL_LANG']['MSC'][$arrChunk[1]])) {
                    return $GLOBALS['TL_LANG']['MSC'][$arrChunk[1]];
                }
            }
        }

        return false;
    }
}
