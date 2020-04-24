/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */
"use strict";

class resourceBookingApp {
    constructor(vueElement, params) {
        new Vue({
            el: vueElement,
            data: {

                // Module options
                opt: [],
                // indicates if application is initialized, switches to true, when fetchData request was fired first time
                // and the request status is 200
                isReady: false,
                // Contains the last response code
                lastResponseStatus: 200,
                // Contains data about available resource types, resources and weeks (week selector)
                filterBoard: null,
                // Indicates if the current user hass logged in as a frontend user
                userIsLoggedIn: false,
                // Contains the logged in user data
                loggedInUser: [],
                // The request token
                requestToken: '',
                // Contains the weekdays
                weekdays: [],
                // Contains the time slots (first col in the booking table)
                timeSlots: [],
                // The cell data of a each row in the booking table
                rows: [],
                // Contains the id
                activeResourceTypeId: 'undefined',
                // Contains the data in an array: id, title, etc.
                activeResourceType: [],
                // Contains the id
                activeResourceId: 'undefined',
                // Contains the data in an array: id, title, etc.
                activeResource: [],
                // Monday current week 00:00 UTC
                activeWeekTstamp: 0,
                // Contains data about the active week: tstampStart, tstampEnd, dateStart, dateEnd, weekNumber, year
                activeWeek: [],
                bookingRepeatsSelection: [],
                bookingModal: [],
                bookingFormValidation: [],
                intervals: [],
                messages: null,
                // Indicates if user is idle
                isIdle: false,
                // Do not run applyFilterRequest() if there is a pending request
                isBusy: false,
            },

            created: function created() {
                let self = this;

                // Detect unsupported browsers
                let ua = window.navigator.userAgent;
                let msie = ua.indexOf('MSIE ');
                if (msie > 0) {
                    alert('This extension is not compatible with your browser. Please use a current browser (like Opera, Firefox, Safari or Google Chrome), that is not out of date.')
                }

                // Post requests require a request token
                self.requestToken = params.requestToken;

                // Show the loading spinner for 2s
                window.setTimeout(function(){
                    self.fetchDataRequest();
                }, 2000);

                // Fetch data from server each 30s
                self.intervals.fetchDataRequest = window.setInterval(function () {
                    if (!self.isIdle && !self.isBusy) {
                        self.fetchDataRequest();
                    }
                }, 30000);

                // Initialize idle detector
                window.setTimeout(function () {
                    self.initializeIdleDetector();
                }, 10000);
            },

            // Watchers
            watch: {
                // Watcher
                isReady: function isOnline(val) {

                },
                activeResourceTypeId: function activeResourceTypeId(newObj, oldObj) {
                    this.applyFilterRequest();
                },
                activeResourceId: function activeResourceId(newObj, oldObj) {
                    this.applyFilterRequest();
                },
                activeWeekTstamp: function activeWeekTstamp(newObj, oldObj) {
                    this.applyFilterRequest();
                }
            },

            methods: {

                /**
                 * Fetch all the data from the server
                 */
                fetchDataRequest: function fetchDataRequest() {

                    let self = this;
                    let action = 'fetchDataRequest';

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('action', action);

                    // Fetch
                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                    .then(function (res) {
                        self.checkResponse(res);
                        return res.json();
                    })
                    .then(function (response) {
                        if (response.status === 'success') {
                            for (let key in response['data']) {
                                self[key] = response['data'][key];
                            }
                        }
                    }).catch(function (error) {
                        self.isReady = false;
                    });
                },

                /**
                 * Apply the filter changes
                 */
                applyFilterRequest: function applyFilterRequest() {

                    let self = this;
                    let action = 'applyFilterRequest';
                    self.isBusy = true;

                    self.toggleBackdrop(true);

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('action', action);
                    data.append('resType', self.activeResourceTypeId);
                    data.append('res', self.activeResourceId);
                    data.append('date', self.activeWeekTstamp);

                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                    .then(function (res) {
                        self.checkResponse(res);
                        return res.json();
                    })
                    .then(function (response) {
                        if (response.status === 'success') {
                            for (let key in response.data) {
                                self[key] = response.data[key];
                            }
                        }
                        self.toggleBackdrop(false);
                        window.setTimeout(function(){
                            self.isBusy = false;
                        },150);
                    })
                    .catch(function (response) {
                        self.isReady = false;
                        self.toggleBackdrop(false);
                        window.setTimeout(function(){
                            self.isBusy = false;
                        },150);
                    });
                },

                /**
                 * Send booking request
                 */
                bookingRequest: function bookingRequest() {

                    let self = this;
                    let action = 'bookingRequest';

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('action', action);
                    data.append('resourceId', self.bookingModal.activeTimeSlot.resourceId);
                    data.append('description', $(self.$el).find('.resource-booking-modal [name="bookingDescription"]').first().val());
                    data.append('bookingRepeatStopWeekTstamp', $(self.$el).find('.booking-repeat-stop-week-tstamp').first().val());

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
                            self.checkResponse(res);
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingModal.message.success = response.message.success;
                                window.setTimeout(function () {
                                    $(self.$el).find('.resource-booking-modal').first().modal('hide');
                                }, 2500);
                            } else {
                                self.bookingModal.message.error = response.message.error;
                            }
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .catch(function (response) {
                            self.isReady = false;
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        });
                },

                /**
                 * Send resource availability request
                 */
                bookingFormValidationRequest: function bookingFormValidationRequest() {
                    let self = this;
                    let action = 'bookingFormValidationRequest';

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('action', action);
                    data.append('resourceId', self.bookingModal.activeTimeSlot.resourceId);
                    data.append('bookingRepeatStopWeekTstamp', $(self.$el).find('.booking-repeat-stop-week-tstamp').first().val());

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
                            self.checkResponse(res);
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingFormValidation = response.data;
                                self.isReady = true;
                            }
                        }).catch(function (response) {
                        self.isReady = false;
                    });

                },

                /**
                 * Send cancel booking request
                 */
                cancelBookingRequest: function cancelBookingRequest() {
                    let self = this;
                    let action = 'cancelBookingRequest';

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('action', action);
                    data.append('bookingId', self.bookingModal.activeTimeSlot.bookingId);

                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                        .then(function (res) {
                            self.checkResponse(res);
                            return res.json();
                        })
                        .then(function (response) {
                            if (response.status === 'success') {
                                self.bookingModal.message.success = response.message.success;
                                window.setTimeout(function () {
                                    $(self.$el).find('.resource-booking-modal').first().modal('hide');
                                }, 2500);
                            } else {
                                self.bookingModal.message.error = response.message.error;
                            }
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .catch(function (response) {
                            self.isReady = false;
                            // Always
                            self.bookingModal.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        });
                },

                /**
                 * Jump to next/previous week
                 * @param tstamp
                 * @param event
                 */
                jumpWeekRequest: function jumpWeekRequest(tstamp, event) {

                    let self = this;
                    event.preventDefault();
                    event.stopPropagation();

                    if(self.isBusy){
                       return false;
                    }


                    // Prevent bubbling invalid requests
                    if(tstamp === self.activeWeekTstamp || tstamp < self.filterBoard.tstampFirstPossibleWeek || tstamp > self.filterBoard.tstampLastPossibleWeek)
                    {
                        return false;
                    }

                    // Vue watcher will trigger self.applyFilterRequest()
                    self.activeWeekTstamp = tstamp;
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
                    self.bookingModal.message = {
                        success: null,
                        error: null,
                    };
                    self.bookingModal.selectedTimeSlots.push(objActiveTimeSlot.bookingCheckboxValue);
                    self.bookingFormValidation = [];

                    // Hide booking preview
                    $(self.$el).find('.booking-preview').first().collapse('hide');
                    window.setTimeout(function () {
                        self.bookingFormValidationRequest();
                    }, 500);

                    $(self.$el).find('.resource-booking-modal').first().on('show.bs.modal', function () {
                        $(self.$el).find('.resource-booking-modal [name="bookingDescription"]').first().val('');
                        $(self.$el).find('.booking-repeat-stop-week-tstamp option').prop('selected', false);
                    });

                    $(self.$el).find('.resource-booking-modal').first().modal('show');
                },

                /**
                 * Add or remove the backdrop
                 * @param blnAdd
                 */
                toggleBackdrop: function toggleBackdrop(blnAdd = true) {
                    if (blnAdd) {
                        $('.modal-backdrop').remove();
                        let backdrop = '<div class="modal-backdrop resource-booking-backdrop show"></div>';
                        $("body").append(backdrop);
                    } else {
                        window.setTimeout(function () {
                            $('.modal-backdrop').remove();
                        }, 100);
                    }
                },

                /**
                 * Check json response
                 * @param status
                 */
                checkResponse: function checkResponse(res) {
                    this.lastResponseStatus = res.status;
                    if (res.status != 200) {
                        this.isReady = false;
                    } else {
                        this.isReady = true;
                    }
                },

                /**
                 * Initialize idle detector
                 */
                initializeIdleDetector: function initializeIdleDetector() {
                    let self = this;
                    $(document).idle({
                        onIdle: function onIdle() {
                            self.isIdle = true;
                        },
                        onActive: function () {
                            self.isIdle = false;
                            self.fetchDataRequest();
                        },
                        // 5min = 300s = 300000 ms
                        idle: 300000,
                    });
                }

            }
        });
    }
}



