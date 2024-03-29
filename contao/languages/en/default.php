<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

// Weekdays
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['monday'] = 'Mon';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['tuesday'] = 'Tue';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['wednesday'] = 'Wed';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['thursday'] = 'Thu';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['friday'] = 'Fri';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['saturday'] = 'Sat';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED']['sunday'] = 'Sun';

$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['monday'] = 'Monday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['tuesday'] = 'Tuesday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['wednesday'] = 'Wednesday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['thursday'] = 'Thursday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['friday'] = 'Friday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['saturday'] = 'Saturday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG']['sunday'] = 'Sunday';

// Forms
$GLOBALS['TL_LANG']['MSC']['weekSelectOptionText'] = 'KW %s/%s: %s - %s';
$GLOBALS['TL_LANG']['MSC']['bookingFor'] = 'Booking for';
$GLOBALS['TL_LANG']['RBB']['formLegendItems'] = 'Select items';
$GLOBALS['TL_LANG']['RBB']['formHelpItems'] = 'Select items.';
$GLOBALS['TL_LANG']['RBB']['formLegendDescription'] = 'Brief description';
$GLOBALS['TL_LANG']['RBB']['formHelpDescription'] = 'Enter a short description (max 50 characters).';
$GLOBALS['TL_LANG']['RBB']['formLegendRepetitions'] = 'Repeat booking until ...?';
$GLOBALS['TL_LANG']['RBB']['formOptionSelectResource'] = 'Select resource';
$GLOBALS['TL_LANG']['RBB']['formOptionSelectResourceType'] = 'Select category';

// Frontend template
$GLOBALS['TL_LANG']['RBB']['1WeekAhead'] = '1 week ahead';
$GLOBALS['TL_LANG']['RBB']['1WeekBack'] = '1 week back';
$GLOBALS['TL_LANG']['RBB']['anonymous'] = 'Anonymous';
$GLOBALS['TL_LANG']['RBB']['book'] = 'book';
$GLOBALS['TL_LANG']['RBB']['bookResource'] = 'Book resource';
$GLOBALS['TL_LANG']['RBB']['bookingIdAndUuid'] = 'booking id/uuid';
$GLOBALS['TL_LANG']['RBB']['cancel'] = 'Cancel';
$GLOBALS['TL_LANG']['RBB']['cancelBooking'] = 'Should the booking be canceled?';
$GLOBALS['TL_LANG']['RBB']['cancelResource'] = 'Cancel booking';
$GLOBALS['TL_LANG']['RBB']['close'] = 'Close';
$GLOBALS['TL_LANG']['RBB']['deleteRepetitions'] = 'Delete repetitions';
$GLOBALS['TL_LANG']['RBB']['fullname'] = 'Name';
$GLOBALS['TL_LANG']['RBB']['invalidDate'] = 'invalid date';
$GLOBALS['TL_LANG']['RBB']['isAvailable'] = 'can still be booked in the desired period.';
$GLOBALS['TL_LANG']['RBB']['loading'] = 'loading...';
$GLOBALS['TL_LANG']['RBB']['loggedInAs'] = 'Logged in as';
$GLOBALS['TL_LANG']['RBB']['pieces'] = 'piece(s)';
$GLOBALS['TL_LANG']['RBB']['resource'] = 'Resource';
$GLOBALS['TL_LANG']['RBB']['resourceType'] = 'Resource type';
$GLOBALS['TL_LANG']['RBB']['showOccupiedResources'] = 'Show already booked resources';
$GLOBALS['TL_LANG']['RBB']['stillAvailable'] = 'still available';
$GLOBALS['TL_LANG']['RBB']['time'] = 'Time';
$GLOBALS['TL_LANG']['RBB']['timeSpan'] = 'Time span';
$GLOBALS['TL_LANG']['RBB']['week'] = 'Week';

// Errors
$GLOBALS['TL_LANG']['RBB']['ERR']['401'] = 'Your session has expired. Access was denied. Please log in again.';
$GLOBALS['TL_LANG']['RBB']['ERR']['bookingNotFound'] = 'Could not find booking with ID %s.';
$GLOBALS['TL_LANG']['RBB']['ERR']['cancelingBookingNotAllowed'] = 'You are not allowed to cancel this booking.';
$GLOBALS['TL_LANG']['RBB']['ERR']['general'] = 'An error has occured. Please check connectivity.';
$GLOBALS['TL_LANG']['RBB']['ERR']['generalBookingError'] = 'Error, while trying to book a resource.';
$GLOBALS['TL_LANG']['RBB']['ERR']['invalidStartOrEndTime'] = 'You\'ve selected a slot with a invalid start- or end time.';
$GLOBALS['TL_LANG']['RBB']['ERR']['notAuthorized'] = 'You are not authorized for this action.';
$GLOBALS['TL_LANG']['RBB']['ERR']['notEnoughItemsAvailable'] = 'There aren\'t enough items available in one ore more slots you\'ve requested.';
$GLOBALS['TL_LANG']['RBB']['ERR']['resourceIsAlreadyFullyBooked'] = 'Resource is already fully booked.';
$GLOBALS['TL_LANG']['RBB']['ERR']['selectBookingDatesPlease'] = 'Select one or more booking time slots please.';
$GLOBALS['TL_LANG']['RBB']['ERR']['slotNotBookable'] = 'You\'ve selected a non bookable slot';
$GLOBALS['TL_LANG']['RBB']['ERR']['somethingWentWrong'] = 'Ups! I\'m sorry! Something went wrong.';
$GLOBALS['TL_LANG']['RBB']['ERR']['thisSlotHasNotEnoughItemsAvailable'] = 'There aren\'t enough items available in this slot.';

// Messages
$GLOBALS['TL_LANG']['RBB']['MSG']['noResourceSelected'] = 'Select a ressource please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['pleaseInsertValidBookingTime'] = 'Insert a valid time (format hh:mm) please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['resourceAvailable'] = 'The resource can still be booked in the desired period.';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectResourcePlease'] = 'Select a resource please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectResourceTypePlease'] = 'Select a resource type please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectValidResourcePlease'] = 'Select a valid resource please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyBookedXItems'] = 'Successfully booked for %s %s items.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyCanceledBooking'] = 'Booking with ID %s has been successfully canceled.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyCanceledBookingAndItsRepetitions'] = 'Successfully canceled booking with ID %s and %s of its repetitions.';
$GLOBALS['TL_LANG']['RBB']['MSG']['windowClosesAutomatically'] = 'This window will close automatically.';
