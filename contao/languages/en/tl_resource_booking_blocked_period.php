<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

// Legends
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['title_legend'] = 'Blocked period';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['date_legend'] = 'Period';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scope_legend'] = 'Scope';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['publish_legend'] = 'Publishing';

// Global operations
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['new'] = ['New', 'Create a new blocked period.'];

// Fields
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['title'] = ['Internal name', 'Enter a name, e.g. "Autumn holidays 2026". It is only displayed in the back end.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['description'] = ['Note in the week calendar', 'Optional text displayed in the blocked time slots of the week calendar, e.g. "Autumn holidays". If the field is empty, the time slots are displayed as blocked without a text.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['dateFrom'] = ['From', 'First blocked day.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['dateTo'] = ['To', 'Last blocked day (inclusive). Leave empty for an unlimited blocked period, e.g. every weekend.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes'] = ['Blocked times', 'Optional: Only block these weekdays and times, e.g. "Saturday" and "Sunday" or "Friday 14:00–24:00". Empty times mean the whole day. Without entries the whole period is blocked.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_weekday'] = ['Weekday'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_timeFrom'] = ['Blocked from (hh:mm)'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['blockedTimes_timeTo'] = ['Blocked until (hh:mm)'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scope'] = ['Applies to', 'Select the resources the blocked period applies to.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['resourceTypes'] = ['Resource types', 'The blocked period applies to all resources of the selected types.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['resources'] = ['Resources', 'The blocked period applies to the selected resources.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['published'] = ['Active', 'The blocked period is active. Inactive blocked periods are ignored.'];

// References
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['all'] = 'All resources';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['resourceTypes'] = 'Selected resource types';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['resources'] = 'Selected resources';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['unlimited'] = 'unlimited';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['daily'] = 'Daily';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['monday'] = 'Monday';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['tuesday'] = 'Tuesday';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['wednesday'] = 'Wednesday';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['thursday'] = 'Thursday';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['friday'] = 'Friday';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['saturday'] = 'Saturday';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['weekdayOptions']['sunday'] = 'Sunday';
