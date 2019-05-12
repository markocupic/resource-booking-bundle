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
        loggedInUser: [],
        resourceIsAvailable: true,
        requestToken: '',
        weekdays: [],
        activeWeek: {},
        timeSlots: [],
        rows: [],
        activeResource: {},
        activeResourceType: {},
        bookingRepeatsSelection: [],
        bookingModal: {
            action: '',
            showConfirmationMsg: false,
            alertSuccess: '',
            alertError: '',
            selectedTimeSlots: []
        },
        form: {}
    },
    created: function created() {
        var self = this;
        self.requestToken = RESOURCE_BOOKING.requestToken; // Load data from server

        self.getDataAll();
        window.setTimeout(function () {
            self.isReady = true;
        }, 800);
    },
    methods: {
        /**
         * Get all the rows from server
         */
        getDataAll: function getDataAll() {
            var self = this;
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'REQUEST_TOKEN': self.requestToken,
                    'action': 'getDataAll'
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {
                    for (var key in response['data']) {
                        self[key] = response['data'][key];
                    }
                }
            });
            xhr.fail(function ($res, $bl) {
                alert("XHR-Request fehlgeschlagen!!!");
            });
            xhr.always(function () {//
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
            self.resourceIsAvailable = true;
            window.setTimeout(function () {
                self.sendResourceAvailabilityRequest();
            }, 500);
            $('#resourceBookingModal [name="bookingDescription"]').val('');
            $('#bookingRepeatStopWeekTstamp option').prop('selected', false);
            $('#bookingRepeatStopWeekTstamp [data-current-week="true"]').prop('selected', 'selected');
            $('#resourceBookingModal').modal('show');
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
            });
            xhr.fail(function () {//
            });
            xhr.always(function () {
                self.bookingModal.showConfirmationMsg = true;
                self.getDataAll();
            });
        },

        /**
         * Send resource availability request
         */
        sendResourceAvailabilityRequest: function sendResourceAvailabilityRequest() {
            var self = this;
            var xhr = $.ajax({
                url: window.location.href,
                type: 'post',
                dataType: 'json',
                data: {
                    'action': 'sendResourceAvailabilityRequest',
                    'REQUEST_TOKEN': self.requestToken,
                    'resourceId': self.bookingModal.activeTimeSlot.resourceId,
                    'bookingDateSelection': self.bookingModal.selectedTimeSlots,
                    'bookingRepeatStopWeekTstamp': $('#bookingRepeatStopWeekTstamp').val()
                }
            });
            xhr.done(function (response) {
                if (response.status === 'success') {
                    self.resourceIsAvailable = response.resourceIsAvailable;
                }
            });
            xhr.fail(function () {//
            });
            xhr.always(function () {//
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
            });
            xhr.fail(function () {//
            });
            xhr.always(function () {
                self.bookingModal.showConfirmationMsg = true;
                self.getDataAll();
            });
        },

        /**
         * submit form on change
         */
        submitForm: function submitForm() {
            var self = this;
            document.getElementById('resourceBookingForm').submit();
        }
    }
});
