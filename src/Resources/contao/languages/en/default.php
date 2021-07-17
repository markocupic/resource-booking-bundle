<?php

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

// Weekdays
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED'][0] = 'Mon';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED'][1] = 'Tue';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED'][2] = 'Wed';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED'][3] = 'Thu';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED'][4] = 'Fri';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED'][5] = 'Sat';
$GLOBALS['TL_LANG']['MSC']['DAYS_SHORTENED'][6] = 'Sun';

$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'][0] = 'Monday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'][1] = 'Tuesday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'][2] = 'Wednesday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'][3] = 'Thursday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'][4] = 'Friday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'][5] = 'Saturday';
$GLOBALS['TL_LANG']['MSC']['DAYS_LONG'][6] = 'Sunday';

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
$GLOBALS['TL_LANG']['RBB']['1WeekBack'] = '1 week back';
$GLOBALS['TL_LANG']['RBB']['1WeekAhead'] = '1 week ahead';
$GLOBALS['TL_LANG']['RBB']['close'] = 'Close';
$GLOBALS['TL_LANG']['RBB']['cancelBooking'] = 'Should the booking be canceled?';
$GLOBALS['TL_LANG']['RBB']['time'] = 'Time';
$GLOBALS['TL_LANG']['RBB']['timeSpan'] = 'Time span';
$GLOBALS['TL_LANG']['RBB']['showOccupiedResources'] = 'Show already booked resources';
$GLOBALS['TL_LANG']['RBB']['isAvailable'] = 'can still be booked in the desired period.';
$GLOBALS['TL_LANG']['RBB']['resource'] = 'Resource';
$GLOBALS['TL_LANG']['RBB']['cancel'] = 'Cancel';
$GLOBALS['TL_LANG']['RBB']['bookResource'] = 'Book resource';
$GLOBALS['TL_LANG']['RBB']['cancelResource'] = 'Cancel booking';
$GLOBALS['TL_LANG']['RBB']['book'] = 'book';
$GLOBALS['TL_LANG']['RBB']['loading'] = 'loading...';
$GLOBALS['TL_LANG']['RBB']['loggedInAs'] = 'Logged in as';
$GLOBALS['TL_LANG']['RBB']['week'] = 'Week';
$GLOBALS['TL_LANG']['RBB']['fullname'] = 'Name';
$GLOBALS['TL_LANG']['RBB']['bookingIdAndUuid'] = 'booking id/uuid';
$GLOBALS['TL_LANG']['RBB']['deleteRepetitions'] = 'Delete repetitions';
$GLOBALS['TL_LANG']['RBB']['invalidDate'] = 'invalid date';
$GLOBALS['TL_LANG']['RBB']['generalError'] = 'An error has occured. Please check connectivity.';
$GLOBALS['TL_LANG']['RBB']['error401'] = 'An error has occured. You are not authorized. Please try to log in.';
$GLOBALS['TL_LANG']['RBB']['pieces'] = 'piece(s)';

// Errors
$GLOBALS['TL_LANG']['RBB']['ERR']['invalidStartOrEndTime'] = 'You\'ve selected a slot with a invalid start- or end time.';
$GLOBALS['TL_LANG']['RBB']['ERR']['slotNotBookable'] = 'You\'ve selected a non bookable slot';
$GLOBALS['TL_LANG']['RBB']['ERR']['generalBookingError'] = 'Error, while trying to book a resource.';
$GLOBALS['TL_LANG']['RBB']['ERR']['cancelingBookingNotAllowed'] = 'You are not allowed to cancel this booking.';
$GLOBALS['TL_LANG']['RBB']['ERR']['selectBookingDatesPlease'] = 'Select one or more booking time slots please.';
$GLOBALS['TL_LANG']['RBB']['ERR']['resourceIsAlreadyFullyBooked'] = 'Resource is already fully booked.';
$GLOBALS['TL_LANG']['RBB']['ERR']['notEnoughItemsAvailable'] = 'The requested resource(s) are fully booked or there are not enough units available.';

// Messages
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyBookedXItems'] = 'Successfully booked for %s %s items.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyCanceledBookingAndItsRepetitions'] = 'Successfully canceled booking with ID %s and %s of its repetitions.';
$GLOBALS['TL_LANG']['RBB']['MSG']['successfullyCanceledBooking'] = 'Booking with ID %s has been successfully canceled.';
$GLOBALS['TL_LANG']['RBB']['MSG']['resourceAvailable'] = 'The resource can still be booked in the desired period.';
$GLOBALS['TL_LANG']['RBB']['MSG']['pleaseInsertValidBookingTime'] = 'Insert a valid time (format hh:mm) please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['noResourceSelected'] = 'Select a ressource please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectResourceTypePlease'] = 'Select a resource type please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectResourcePlease'] = 'Select a resource please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['selectValidResourcePlease'] = 'Select a valid resource please.';
$GLOBALS['TL_LANG']['RBB']['MSG']['windowClosesAutomatically'] = 'This window will close automatically.';
