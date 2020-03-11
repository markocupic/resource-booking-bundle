/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */
"use strict";

class resourceBookingApp {
    constructor(vueElement, params) {

        /**
         * Constants
         */
        const Selector = {
            RESOURCE_BOOKING_MODAL: '.resource-booking-modal',
            RESOURCE_BOOKING_MODAL_INPUT_BOOKING_DESCRIPTION: '.resource-booking-modal .input-booking-description',
            AUTO_LOGOUT_MODAL: '.auto-logout-modal',
            BOOKING_REPEAT_STOP_WEEK_TSTAMP: '.booking-repeat-stop-week-tstamp',
            BOOKING_REPEAT_STOP_WEEK_TSTAMP_OPTION: '.booking-repeat-stop-week-tstamp option',
            BOOKING_PREVIEW: '.booking-preview',
            MODAL_BACKDROP: '.modal-backdrop',
        }

        const ClassName = {

            MODAL_BACKDROP: 'modal-backdrop',
            SHOW: 'show',
        }

        new Vue({
            el: vueElement,
            data: {
                opt: [],
                isReady: false,
                filterBoard: null,
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
                let self = this;

                // Detect unsupported browders
                var ua = window.navigator.userAgent;
                var msie = ua.indexOf('MSIE ');
                if (msie > 0) {
                    alert('This extension is not compatible with your browser. Please use a current browser (like Opera, Firefox, Safari or Google Chrome), that is not out of date.')
                }

                // Post requests require a request token
                params.requestToken = params.requestToken;

                // Fetch data from server each 30s
                self.fetchDataRequest();
                self.intervals.fetchDataRequest = window.setInterval(function () {
                    self.fetchDataRequest();
                }, 30000);

                // Initialize idle detector
                window.setTimeout(function () {
                    self.initializeIdleDetector();
                }, 10000);
            },

            // Watchers
            watch: {
                // Watcher
                isOnline: function isOnline(val) {
                    let self = this;

                    if (val === false) {
                        // Clear interval
                        clearInterval(self.intervals.fetchDataRequest);

                        // Logout user after 7 min (420000 ms) of idle time
                        self.sendLogoutRequest();
                        window.setTimeout(function () {
                            // Close booking modal if it is still open
                            $(self.$el).find(Selector.RESOURCE_BOOKING_MODAL).first().modal('hide');
                            window.setTimeout(function () {
                                $(self.$el).find('.' + Selector.AUTO_LOGOUT_MODAL).first().on('hidden.bs.modal', function () {
                                    if (self.opt.autologout) {
                                        location.href = self.opt.autologoutRedirect;
                                    } else {
                                        location.href = '';
                                    }
                                });
                                $(self.$el).find(Selector.AUTO_LOGOUT_MODAL).first().modal('show');
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
                    let self = this;

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('action', 'fetchDataRequest');

                    // Fetch
                    fetch(window.location.href,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                for (let key in response['data']) {
                                    self[key] = response['data'][key];
                                }
                                self.isReady = true;
                            }

                            self.isOnline = true;
                        }).catch(function (error) {
                        self.isOnline = false;
                    });
                },

                /**
                 * Send booking request
                 */
                sendBookingRequest: function sendBookingRequest() {

                    let self = this;

                    let data = new FormData();
                    data.append('action', 'sendBookingRequest');
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('resourceId', self.bookingModal.activeTimeSlot.resourceId);
                    data.append('description', $(self.$el).find(Selector.RESOURCE_BOOKING_MODAL_INPUT_BOOKING_DESCRIPTION).first().val());
                    data.append('bookingRepeatStopWeekTstamp', $(self.$el).find(Selector.BOOKING_REPEAT_STOP_WEEK_TSTAMP).first().val());

                    let i;
                    for (i = 0; i < self.bookingModal.selectedTimeSlots.length; i++) {
                        data.append('bookingDateSelection[]', self.bookingModal.selectedTimeSlots[i]);
                    }

                    fetch(window.location.href,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingModal.alertSuccess = response.alertSuccess;
                                window.setTimeout(function () {
                                    $(self.$el).find(Selector.RESOURCE_BOOKING_MODAL).first().modal('hide');
                                }, 2500);
                            } else {
                                self.bookingModal.alertError = response.alertError;
                            }
                            self.isOnline = true;
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .catch(function (response) {
                            self.isOnline = false;
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        });
                },

                /**
                 * Send resource availability request
                 */
                sendBookingFormValidationRequest: function sendBookingFormValidationRequest() {
                    let self = this;

                    let data = new FormData();
                    data.append('action', 'sendBookingFormValidationRequest');
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('resourceId', self.bookingModal.activeTimeSlot.resourceId);
                    data.append('bookingRepeatStopWeekTstamp', $(self.$el).find(Selector.BOOKING_REPEAT_STOP_WEEK_TSTAMP).first().val());

                    let i;
                    for (i = 0; i < self.bookingModal.selectedTimeSlots.length; i++) {
                        data.append('bookingDateSelection[]', self.bookingModal.selectedTimeSlots[i]);
                    }

                    fetch(window.location.href,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingFormValidation = response.data;
                                self.isOnline = true;
                            } else {
                                self.isOnline = false;
                            }
                        }).catch(function (response) {
                        self.isOnline = false;
                    });

                },

                /**
                 * Send cancel booking request
                 */
                sendCancelBookingRequest: function sendCancelBookingRequest() {
                    let self = this;

                    let data = new FormData();
                    data.append('action', 'sendCancelBookingRequest');
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('bookingId', self.bookingModal.activeTimeSlot.bookingId);

                    fetch(window.location.href,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingModal.alertSuccess = response.alertSuccess;
                                window.setTimeout(function () {
                                    $(self.$el).find(Selector.RESOURCE_BOOKING_MODAL).first().modal('hide');
                                }, 2500);
                            } else {
                                self.bookingModal.alertError = response.alertError;
                            }
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .catch(function (response) {
                            self.isOnline = false;
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        });
                },

                /**
                 * Send logout request
                 */
                sendLogoutRequest: function sendLogoutRequest() {

                    let self = this;

                    let data = new FormData();
                    data.append('action', 'sendLogoutRequest');
                    data.append('REQUEST_TOKEN', self.requestToken);

                    fetch(window.location.href,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            // Always
                            self.isOnline = false;
                            self.userLoggedOut = true;
                        })
                        .catch(function (response) {
                            // Always
                            self.isOnline = false;
                            self.userLoggedOut = true;
                        });
                },

                /**
                 * Apply the filter changes
                 */
                sendApplyFilterRequest: function sendApplyFilterRequest() {

                    let self = this;
                    let data = new FormData();
                    data.append('action', 'sendApplyFilterRequest');
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('resType', self.activeResourceTypeId);
                    data.append('res', self.activeResourceId);
                    data.append('date', self.activeWeekTstamp);

                    fetch(window.location.href,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                for (let key in response.data) {
                                    self[key] = response.data[key];
                                }
                            }
                            self.isOnline = true;
                        })
                        .catch(function (response) {
                            self.isOnline = false;
                        });
                },

                /**
                 * Jump to next/previous week
                 * @param tstamp
                 * @param event
                 */
                sendJumpWeekRequest: function sendJumpWeekRequest(tstamp, event) {

                    let self = this;
                    event.preventDefault();
                    $('.' + ClassName.MODAL_BACKDROP).remove();

                    // Inject backdrop to DOM
                    let backdrop = document.createElement("div");
                    backdrop.classList.add(ClassName.MODAL_BACKDROP);
                    backdrop.classList.add(ClassName.SHOW);
                    $("body").append(backdrop);

                    let data = new FormData();
                    data.append('action', 'sendJumpWeekRequest');
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('resType', self.activeResourceTypeId);
                    data.append('res', self.activeResourceId);
                    data.append('date', tstamp);


                    fetch(window.location.href,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                        .then(function (res) {
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                for (let key in response.data) {
                                    self[key] = response.data[key];
                                }
                            }
                            self.isOnline = true;
                            // Always
                            window.setTimeout(function () {
                                $(Selector.MODAL_BACKDROP).remove();
                            }, 200);
                        })
                        .catch(function (response) {
                            self.isOnline = false;
                            // Always
                            window.setTimeout(function () {
                                $(Selector.MODAL_BACKDROP).remove();
                            }, 200);
                        });
                },

                /**
                 * Initialize idle detector
                 */
                initializeIdleDetector: function initializeIdleDetector() {
                    let self = this;
                    if (self.opt.autologout && parseInt(self.opt.autologoutDelay) > 0) {
                        $(document).idle({
                            onIdle: function onIdle() {
                                self.sendLogoutRequest();
                            },
                            idle: parseInt(self.opt.autologoutDelay) * 1000
                        });
                    }
                },

                /**
                 * Open booking modal window
                 * @param objActiveTimeSlot
                 * @param action
                 */
                openBookingModal: function openBookingModal(objActiveTimeSlot, action) {
                    let self = this;

                    self.bookingModal.selectedTimeSlots = [];
                    self.bookingModal.action = action;
                    self.bookingModal.showConfirmationMsg = false;
                    self.bookingModal.activeTimeSlot = objActiveTimeSlot;
                    self.bookingModal.alertSuccess = '';
                    self.bookingModal.alertError = '';
                    self.bookingModal.selectedTimeSlots.push(objActiveTimeSlot.bookingCheckboxValue);
                    self.bookingFormValidation = [];

                    // Hide booking preview
                    $(self.$el).find(Selector.BOOKING_PREVIEW).first().collapse('hide');
                    window.setTimeout(function () {
                        self.sendBookingFormValidationRequest();
                    }, 500);

                    $(self.$el).find(Selector.RESOURCE_BOOKING_MODAL).first().on('show.bs.modal', function () {
                        $(self.$el).find(Selector.RESOURCE_BOOKING_MODAL_INPUT_BOOKING_DESCRIPTION).first().val('');
                        $(self.$el).find(Selector.BOOKING_REPEAT_STOP_WEEK_TSTAMP_OPTION).prop('selected', false);
                    });

                    $(self.$el).find(Selector.RESOURCE_BOOKING_MODAL).first().modal('show');
                }
            }
        });
    }
};



