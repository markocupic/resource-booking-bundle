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
        activeResourceTypeId: '',
        activeResourceId: '',
        activeWeekTstamp: 0,
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

        // Post requests require a request token
        self.requestToken = RESOURCE_BOOKING.requestToken;

        // Fetch data from server each 30s
        self.fetchDataRequest();
        self.intervals.fetchDataRequest = window.setInterval(function () {
            self.fetchDataRequest();
        }, 30000);

        // Initialize idle detector
        self.initializeIdleDetector();
    },
    // Watchers
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
        },
        activeResourceTypeId: function activeResourceTypeId(newObj, oldObj) {
            this.sendApplyFilterRequest();
        },
        activeResourceId: function activeResourceId(newObj, oldObj) {
            this.sendApplyFilterRequest();
        },
        activeWeekTstamp: function activeWeekTstamp(newObj, oldObj) {
            this.sendApplyFilterRequest();
        }
    },
    methods: {
        /**
         * Fetch all the data from the server
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
                },
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
            var data = new FormData();
            data.append('action', 'sendBookingRequest');
            data.append('REQUEST_TOKEN', self.requestToken);
            data.append('resourceId', self.bookingModal.activeTimeSlot.resourceId);
            data.append('description', $('#resourceBookingModal [name="bookingDescription"]').val());
            data.append('bookingRepeatStopWeekTstamp', $('#bookingRepeatStopWeekTstamp').val());
            var i;
            for (i = 0; i < self.bookingModal.selectedTimeSlots.length; i++) {
                data.append('bookingDateSelection[]', self.bookingModal.selectedTimeSlots[i]);
            }

            axios({
                method: 'post',
                url: window.location.href,
                data: data,
                headers: {
                    'X_REQUESTED_WITH': 'XMLHttpRequest',
                }
            }).then(function (response) {
                if (response.data.status === 'success') {
                    self.bookingModal.alertSuccess = response.data.alertSuccess;
                    window.setTimeout(function () {
                        $('#resourceBookingModal').modal('hide');
                    }, 2500);
                } else {
                    self.bookingModal.alertError = response.data.alertError;
                }
                self.isOnline = true;
            }).catch(function (response) {
                self.isOnline = false;
            }).then(function (response) {
                // Always
                self.bookingModal.showConfirmationMsg = true;
                self.fetchDataRequest();
            });
        },

        /**
         * Send resource availability request
         */
        sendBookingFormValidationRequest: function sendBookingFormValidationRequest() {
            var self = this;
            var data = new FormData();
            data.append('action', 'sendBookingFormValidationRequest');
            data.append('REQUEST_TOKEN', self.requestToken);
            data.append('resourceId', self.bookingModal.activeTimeSlot.resourceId);
            data.append('bookingRepeatStopWeekTstamp', $('#bookingRepeatStopWeekTstamp').val());
            var i;
            for (i = 0; i < self.bookingModal.selectedTimeSlots.length; i++) {
                data.append('bookingDateSelection[]', self.bookingModal.selectedTimeSlots[i]);
            }

            axios({
                method: 'post',
                url: window.location.href,
                data: data,
                headers: {
                    'X_REQUESTED_WITH': 'XMLHttpRequest',
                }
            }).then(function (response) {
                if (response.data.status === 'success') {
                    self.bookingFormValidation = response.data.data;
                    self.isOnline = true;
                } else {
                    self.isOnline = false;
                }
            }).catch(function (response) {
                self.isOnline = false;
            }).then(function (response) {
                // Always
            });
        },

        /**
         * Send cancel booking request
         */
        sendCancelBookingRequest: function sendCancelBookingRequest() {
            var self = this;
            var data = new FormData();
            data.append('action', 'sendCancelBookingRequest');
            data.append('REQUEST_TOKEN', self.requestToken);
            data.append('bookingId', self.bookingModal.activeTimeSlot.bookingId);

            axios({
                method: 'post',
                url: window.location.href,
                data: data,
                headers: {
                    'X_REQUESTED_WITH': 'XMLHttpRequest',
                }
            }).then(function (response) {
                if (response.data.status === 'success') {
                    self.bookingModal.alertSuccess = response.data.alertSuccess;
                    window.setTimeout(function () {
                        $('#resourceBookingModal').modal('hide');
                    }, 2500);
                } else {
                    self.bookingModal.alertError = response.data.alertError;
                }
            }).catch(function (response) {
                self.isOnline = false;
            }).then(function (response) {
                // Always
                self.bookingModal.showConfirmationMsg = true;
                self.fetchDataRequest();
            });
        },

        /**
         * Send logout request
         */
        sendLogoutRequest: function sendLogoutRequest() {

            var self = this;
            var data = new FormData();
            data.append('action', 'sendLogoutRequest');
            data.append('REQUEST_TOKEN', self.requestToken);

            axios({
                method: 'post',
                url: window.location.href,
                data: data,
                headers: {
                    'X_REQUESTED_WITH': 'XMLHttpRequest',
                }
            }).then(function (response) {
            }).catch(function (response) {
            }).then(function (response) {
                // Always
                self.isOnline = false;
                self.userLoggedOut = true;
            });
        },

        /**
         * Apply the filter changes
         */
        sendApplyFilterRequest: function sendApplyFilterRequest() {

            var self = this;
            var data = new FormData();
            data.append('action', 'sendApplyFilterRequest');
            data.append('REQUEST_TOKEN', self.requestToken);
            data.append('resType', self.activeResourceTypeId);
            data.append('res', self.activeResourceId);
            data.append('date', self.activeWeekTstamp);

            axios({
                method: 'post',
                url: window.location.href,
                data: data,
                headers: {
                    'X_REQUESTED_WITH': 'XMLHttpRequest',
                }
            }).then(function (response) {
                if (response.data.status === 'success') {
                    for (var key in response.data.data) {
                        self[key] = response.data.data[key];
                    }
                }
                self.isOnline = true;
            }).catch(function (response) {
                self.isOnline = false;
            }).then(function (response) {
                // Always
            });
        },

        /**
         * Jump to next/previous week
         * @param tstamp
         * @param event
         */
        sendJumpWeekRequest: function sendJumpWeekRequest(tstamp, event) {

            var self = this;
            event.preventDefault();
            $('.modal-backdrop').remove();
            var backdrop = '<div class="modal-backdrop show"></div>';
            $("body").append(backdrop);

            var data = new FormData();
            data.append('action', 'sendJumpWeekRequest');
            data.append('REQUEST_TOKEN', self.requestToken);
            data.append('resType', self.activeResourceTypeId);
            data.append('res', self.activeResourceId);
            data.append('date', tstamp);
            axios({
                method: 'post',
                url: window.location.href,
                data: data,
                headers: {
                    'X_REQUESTED_WITH': 'XMLHttpRequest',
                }
            }).then(function (response) {
                if (response.data.status === 'success') {
                    for (var key in response.data.data) {
                        self[key] = response.data.data[key];
                    }
                }
                self.isOnline = true;
            }).catch(function (response) {
                self.isOnline = false;
            }).then(function (response) {
                // Always
                window.setTimeout(function () {
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
                onIdle: function onIdle() {
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
        }
    }
});
