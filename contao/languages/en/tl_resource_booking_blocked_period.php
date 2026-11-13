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
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['title'] = ['Title', 'Enter a title, e.g. "Autumn holidays". It is displayed in the blocked cells of the week calendar.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['description'] = ['Description', 'An optional internal description.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['dateFrom'] = ['From', 'First blocked day.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['dateTo'] = ['To', 'Last blocked day (inclusive).'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['addTimeRange'] = ['Block certain times only', 'Blocks only the time slots within the given times, e.g. afternoons only. The times apply to every day of the period.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['timeFrom'] = ['Time from', 'Enter the start time in the format hh:mm, e.g. 12:00.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['timeTo'] = ['Time to', 'Enter the end time in the format hh:mm, e.g. 18:00. Time slots overlapping this time span are blocked.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scope'] = ['Applies to', 'Select the resources the blocked period applies to.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['resourceTypes'] = ['Resource types', 'The blocked period applies to all resources of the selected types.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['resources'] = ['Resources', 'The blocked period applies to the selected resources.'];
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['published'] = ['Active', 'The blocked period is active. Inactive blocked periods are ignored.'];

// References
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['all'] = 'All resources';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['resourceTypes'] = 'Selected resource types';
$GLOBALS['TL_LANG']['tl_resource_booking_blocked_period']['scopeOptions']['resources'] = 'Selected resources';
