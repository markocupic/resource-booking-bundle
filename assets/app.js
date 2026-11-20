/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

import {ResourceBookingApp} from './rbb/ResourceBookingApp.js';

// The module template creates the week calendar: new ResourceBookingApp(element, options)
if (typeof window.ResourceBookingApp !== 'function') {
    window.ResourceBookingApp = ResourceBookingApp;
}
