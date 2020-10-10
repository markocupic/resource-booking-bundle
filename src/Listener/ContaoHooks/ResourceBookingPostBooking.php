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

use Contao\Date;
use Contao\FrontendUser;
use Markocupic\ResourceBookingBundle\Ajax\AjaxHandler;
use Model\Collection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ResourceBookingPostBooking.
 */
class ResourceBookingPostBooking
{
    public function onPostBooking(Collection $objBookingCollection, Request $request, ?FrontendUser $objUser, AjaxHandler $objAjaxHandler): void
    {
        // For demo usage only

        /*
        while ($objBookingCollection->next())
        {
            if ($objUser !== null)
            {
                // Send notifications, manipulate database
                // or do some other insane stuff
                $strMessage = sprintf(
                    'Dear %s %s' ."\n". 'You have successfully booked %s on %s from %s to %s.',
                    $objUser->firstname,
                    $objUser->lastname,
                    $objBookingCollection->getRelated('pid')->title,
                    Date::parse('d.m.Y', $objBookingCollection->startTime),
                    Date::parse('H:i', $objBookingCollection->startTime),
                    Date::parse('H:i', $objBookingCollection->endTime)
                );
                mail(
                    $objUser->email,
                    utf8_decode((string) $objBookingCollection->title),
                    utf8_decode((string) $strMessage)
                );
            }
        }
         */
    }
}
