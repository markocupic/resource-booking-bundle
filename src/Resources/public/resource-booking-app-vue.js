/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */
"use strict";

var resourceBookingApp = new Vue({
    el: '#resourceBookingApp',
    data: {
        isReady: false,
        filterBoard: null,
        // idle time in milliseconds
        idleTimeLimit: 420000,
        userLoggedOut: false,
        isOnline: false,
        loggedInUser: [],
        requestToken: '',
        weekdays: [],
        timeSlots: [],
        rows: [],
        activeResourceType: [],
        activeResource: [],
        activeWeek: [],
        bookingRepeatsSelection: [],
        bookingFormValidation: [],
        bookingModal: {},
        intervals: [],
        messages: null,
    },
    created: function created() {
        var self = this;
        self.requestToken = RESOURCE_BOOKING.requestToken; // Load data from server

        // Get data from server each 15s
        self.fetchDataRequest();
        self.intervals.fetchDataRequest = window.setInterval(function () {
            self.fetchDataRequest();
        }, 15000);

        // Initialize idle detector
        self.initializeIdleDetector();


    },
    watch: {
        // Watcher
        isOnline: function isOnline(val) {
            var self = this;
            if (val === false) {
                // Clear interval
                clearInterval(self.intervals.fetchDataRequest);

                // Logout user after 7 min (420000 ms) of idle time
                self.sendLogoutRequest();
                window.setTimeout(function () {
                    // Close booking modal if it is still open
                    $('#resourceBookingModal').modal('hide');
                    window.setTimeout(function () {
                        $('#autoLogoutModal').on('hidden.bs.modal', function () {
                            location.reload();
                        });
                        $('#autoLogoutModal').modal('show');
                    }, 100);
                }, 400);
            }
        }
    },
    methods: {
        /**
         * Load all the data from the server
         */
        fetchDataRequest: function fetchDataRequest() {
            var self = this;
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'REQUEST_TOKEN': self.requestToken,
                    'action': 'fetchDataRequest'
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {
                    for (var key in response['data']) {
                        self[key] = response['data'][key];
                    }
                    self.isReady = true;
                }
                self.isOnline = true;
            });
            xhr.fail(function ($res, $bl) {
                self.isOnline = false;
            });
        },

        /**
         * Send booking request
         */
        sendBookingRequest: function sendBookingRequest() {
            var self = this;
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'sendBookingRequest',
                    'REQUEST_TOKEN': self.requestToken,
                    'resourceId': self.bookingModal.activeTimeSlot.resourceId,
                    'description': $('#resourceBookingModal [name="bookingDescription"]').val(),
                    'bookingDateSelection': self.bookingModal.selectedTimeSlots,
                    'bookingRepeatStopWeekTstamp': $('#bookingRepeatStopWeekTstamp').val()
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {
                    self.bookingModal.alertSuccess = response.alertSuccess;
                    window.setTimeout(function () {
                        $('#resourceBookingModal').modal('hide');
                    }, 2500);
                } else {
                    self.bookingModal.alertError = response.alertError;
                }
                self.isOnline = true;
            });
            xhr.fail(function () {
                self.isOnline = false;
            });
            xhr.always(function () {
                self.bookingModal.showConfirmationMsg = true;
                self.fetchDataRequest();
            });
        },

        /**
         * Send resource availability request
         */
        sendBookingFormValidationRequest: function sendBookingFormValidationRequest() {
            var self = this;
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'sendBookingFormValidationRequest',
                    'REQUEST_TOKEN': self.requestToken,
                    'resourceId': self.bookingModal.activeTimeSlot.resourceId,
                    'bookingDateSelection': self.bookingModal.selectedTimeSlots,
                    'bookingRepeatStopWeekTstamp': $('#bookingRepeatStopWeekTstamp').val()
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {

                    self.bookingFormValidation = response.data;
                }
                self.isOnline = true;
            });
            xhr.fail(function () {
                self.isOnline = false;
            });
        },

        /**
         * Send cancel booking request
         */
        sendCancelBookingRequest: function sendCancelBookingRequest() {
            var self = this;
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'sendCancelBookingRequest',
                    'REQUEST_TOKEN': self.requestToken,
                    'bookingId': self.bookingModal.activeTimeSlot.bookingId
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {
                    self.bookingModal.alertSuccess = response.alertSuccess;
                    window.setTimeout(function () {
                        $('#resourceBookingModal').modal('hide');
                    }, 2500);
                } else {
                    self.bookingModal.alertError = response.alertError;
                }
                self.isOnline = true;
            });
            xhr.fail(function () {
                self.isOnline = false;
            });
            xhr.always(function () {
                self.bookingModal.showConfirmationMsg = true;
                self.fetchDataRequest();
            });
        },

        /**
         * Send logout request
         */
        sendLogoutRequest: function sendLogoutRequest() {
            var self = this;
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'sendLogoutRequest',
                    'REQUEST_TOKEN': self.requestToken,
                }
            });
            xhr.always(function () {
                self.isOnline = false;
                self.userLoggedOut = true;
            });
        },

        /**
         * Apply the filter changes
         * @param tstamp
         */
        sendApplyFilterRequest: function sendApplyFilterRequest(tstamp) {

            var self = this;
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'resType': $('#resourceBookingForm [name="resType"]').val(),
                    'res': $('#resourceBookingForm [name="res"]').val(),
                    'date': tstamp > 0 ? tstamp : $('#resourceBookingForm [name="date"]').val(),
                    'action': 'sendApplyFilterRequest',
                    'REQUEST_TOKEN': self.requestToken,
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {
                    for (var key in response['data']) {
                        self[key] = response['data'][key];
                    }
                }
                self.isOnline = true;
            });
            xhr.fail(function () {
                self.isOnline = false;
            });

        },

        /**
         * Jump to next/previous week
         * @param tstamp
         */
        sendJumpWeekRequest: function sendJumpWeekRequest(tstamp, event) {
            $('.modal-backdrop').remove();
            var backdrop = '<div class="modal-backdrop show"></div>';
            $("body").append(backdrop);
            var self = this;
            event.preventDefault();
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'resType': self.activeResourceType.id,
                    'res': self.activeResource.id,
                    'date': tstamp,
                    'action': 'sendApplyFilterRequest',
                    'REQUEST_TOKEN': self.requestToken,
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {
                    for (var key in response['data']) {
                        self[key] = response['data'][key];
                    }
                }
                self.isOnline = true;
            });
            xhr.fail(function () {
                self.isOnline = false;
            });
            xhr.always(function () {
                window.setTimeout(
                    function () {
                        $('.modal-backdrop').remove();
                    }, 200);
            });
        },

        /**
         * Initialize idle detector
         */
        initializeIdleDetector: function initializeIdleDetector() {
            var self = this;
            $(document).idle({
                onIdle: function () {
                    self.sendLogoutRequest();
                },
                idle: self.idleTimeLimit
            });
        },

        /**
         * Open booking modal window
         * @param objActiveTimeSlot
         * @param action
         */
        openBookingModal: function openBookingModal(objActiveTimeSlot, action) {
            var self = this;
            self.bookingModal.selectedTimeSlots = [];
            self.bookingModal.action = action;
            self.bookingModal.showConfirmationMsg = false;
            self.bookingModal.activeTimeSlot = objActiveTimeSlot;
            self.bookingModal.alertSuccess = '';
            self.bookingModal.alertError = '';
            self.bookingModal.selectedTimeSlots.push(objActiveTimeSlot.bookingCheckboxValue);
            self.bookingFormValidation = [];

            // Hide booking preview
            $('#bookingPreview').collapse('hide');

            window.setTimeout(function () {
                self.sendBookingFormValidationRequest();
            }, 500);

            $('#resourceBookingModal').on('show.bs.modal', function () {
                $('#resourceBookingModal [name="bookingDescription"]').val('');
                $('#bookingRepeatStopWeekTstamp option').prop('selected', false);
            });
            $('#resourceBookingModal').modal('show');

        },
    }
});
