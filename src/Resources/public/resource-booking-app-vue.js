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
                // Indicate the mode
                mode: 'main-window',
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
                // The module id
                moduleId: '',
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
                bookingWindow: [],
                bookingFormValidation: [],
                intervals: [],
                messages: null,
                // Indicates if user is idle
                isIdle: false,
                // Do not run fetchDataRequest() if there is a pending request
                isBusy: false,

            },

            created: function created() {
                let self = this;

                // Detect unsupported browsers
                let ua = window.navigator.userAgent;
                let msie = ua.indexOf('MSIE ');
                if (msie > 0) {
                    alert('This plugin is not compatible with your browser. Please use a current browser (like Opera, Firefox, Safari or Google Chrome), that is not out of date.')
                }

                // Post requests require a request token
                self.requestToken = params.requestToken;

                // Post reqests require moduleId
                self.moduleId = params.moduleId;


                // Show the loading spinner for 2s
                window.setTimeout(function () {
                    self.fetchDataRequest();
                }, 2000);

                // Fetch data from server each 15s
                self.intervals.fetchDataRequest = window.setInterval(function () {
                    if (!self.isIdle && !self.isBusy) {
                        self.fetchDataRequest();
                    }
                }, 15000);

                // Initialize idle detector
                // Idle after 5 min
                let idleAfter = 300000;
                window.setTimeout(function () {
                    self.initializeIdleDetector(idleAfter);
                }, 10000);

                document.addEventListener('keyup', function (evt) {
                    if (evt.keyCode === 27 && self.mode === 'booking-window') {
                        self.hideBookingWindow();
                    }
                });
            },

            // Watchers
            watch: {
                // Watcher
                isReady: function isOnline(val) {
                    //
                },
                activeResourceTypeId: function activeResourceTypeId(newObj, oldObj) {
                    this.applyFilterRequest(newObj, this.activeResourceId, this.activeWeekTstamp);
                },
                activeResourceId: function activeResourceId(newObj, oldObj) {
                    this.applyFilterRequest(this.activeResourceTypeId, newObj, this.activeWeekTstamp);
                },
                activeWeekTstamp: function activeWeekTstamp(newObj, oldObj) {
                    this.applyFilterRequest(this.activeResourceTypeId, this.activeResourceId, newObj);
                }
            },

            methods: {

                /**
                 * Fetch all the data from the server
                 */
                fetchDataRequest: function fetchDataRequest() {

                    let self = this;
                    let action = 'fetchDataRequest';
                    self.isBusy = true;

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('action', action);
                    data.append('moduleId', self.moduleId);

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
                            return response;
                        })
                        .then(function (response) {
                            self.isReady = true;
                            self.isBusy = false;

                        })
                        .catch(function (error) {
                            self.isReady = false;
                            self.isBusy = false;
                        });
                },

                /**
                 * Apply the filter changes
                 */
                applyFilterRequest: function applyFilterRequest(activeResourceTypeId, activeResourceId, activeWeekTstamp) {

                    let self = this;
                    let action = 'applyFilterRequest';
                    self.isBusy = true;

                    self.toggleBackdrop(true);

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.requestToken);
                    data.append('action', action);
                    data.append('resType', activeResourceTypeId);
                    data.append('res', activeResourceId);
                    data.append('date', activeWeekTstamp);
                    data.append('moduleId', self.moduleId);

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
                            return response;
                        })
                        .then(function (response) {
                            self.toggleBackdrop(false);
                            self.isBusy = false;
                        })
                        .catch(function (response) {
                            self.toggleBackdrop(false);
                            self.isBusy = false;
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
                    data.append('resourceId', self.bookingWindow.activeTimeSlot.resourceId);
                    data.append('description', $(self.$el).find('.booking-window [name="bookingDescription"]').first().val());
                    data.append('bookingRepeatStopWeekTstamp', $(self.$el).find('.booking-repeat-stop-week-tstamp').first().val());
                    data.append('moduleId', self.moduleId);

                    let i;
                    for (i = 0; i < self.bookingWindow.selectedTimeSlots.length; i++) {
                        data.append('bookingDateSelection[]', self.bookingWindow.selectedTimeSlots[i]);
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
                                self.bookingWindow.message.success = response.message.success;
                                window.setTimeout(function () {
                                    self.mode = 'main-window';
                                }, 2500);
                            } else {
                                self.bookingWindow.message.error = response.message.error;
                            }
                            // Always
                            self.bookingWindow.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .catch(function (response) {
                            self.isReady = false;
                            // Always
                            self.bookingWindow.showConfirmationMsg = true;
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
                    data.append('resourceId', self.bookingWindow.activeTimeSlot.resourceId);
                    data.append('bookingRepeatStopWeekTstamp', $(self.$el).find('.booking-repeat-stop-week-tstamp').first().val());
                    data.append('moduleId', self.moduleId);

                    let i;
                    for (i = 0; i < self.bookingWindow.selectedTimeSlots.length; i++) {
                        data.append('bookingDateSelection[]', self.bookingWindow.selectedTimeSlots[i]);
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
                        })
                        .catch(function (response) {
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
                    data.append('bookingId', self.bookingWindow.activeTimeSlot.bookingId);
                    data.append('deleteBookingsWithSameBookingUuid', self.bookingWindow.deleteBookingsWithSameBookingUuid);
                    data.append('moduleId', self.moduleId);

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
                                self.bookingWindow.message.success = response.message.success;
                                window.setTimeout(function () {
                                    self.mode = 'main-window';
                                }, 2500);
                            } else {
                                self.bookingWindow.message.error = response.message.error;
                            }
                            // Always
                            self.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                            self.bookingWindow.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .catch(function (response) {
                            self.isReady = false;
                            // Always
                            self.bookingWindow.showConfirmationMsg = true;
                            self.fetchDataRequest();
                            self.bookingWindow.deleteBookingsWithSameBookingUuid = false;
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

                    if (self.isBusy) {
                        return false;
                    }


                    // Prevent bubbling invalid requests
                    if (tstamp === self.activeWeekTstamp || tstamp < self.filterBoard.tstampFirstPossibleWeek || tstamp > self.filterBoard.tstampLastPossibleWeek) {
                        return false;
                    }

                    // Vue watcher will trigger self.applyFilterRequest()
                    self.activeWeekTstamp = tstamp;
                },


                /**
                 * Open booking window
                 * @param objActiveTimeSlot
                 * @param action
                 */
                openBookingWindow: function openBookingWindow(objActiveTimeSlot, action) {
                    let self = this;

                    self.mode = 'booking-window';

                    self.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                    self.bookingWindow.selectedTimeSlots = [];
                    self.bookingWindow.action = action;
                    self.bookingWindow.showConfirmationMsg = false;
                    self.bookingWindow.activeTimeSlot = objActiveTimeSlot;
                    self.bookingWindow.message = {
                        success: null,
                        error: null,
                    };
                    self.bookingWindow.selectedTimeSlots.push(objActiveTimeSlot.bookingCheckboxValue);
                    self.bookingFormValidation = [];

                    // Hide booking preview
                    $(self.$el).find('.booking-preview').first().collapse('hide');
                    window.setTimeout(function () {
                        self.bookingFormValidationRequest();
                    }, 500);

                    // Switch window
                    self.mode = 'booking-window';
                    $(self.$el).find('.booking-window [name="bookingDescription"]').first().val('');
                    $(self.$el).find('.booking-window .booking-repeat-stop-week-tstamp option').prop('selected', false);

                },

                /**
                 * Hide booking window
                 */
                hideBookingWindow: function hideBookingWindow() {
                    this.mode = 'main-window';
                },


                /**
                 * Add or remove the backdrop
                 * @param blnAdd
                 */
                toggleBackdrop: function toggleBackdrop(blnAdd = true) {
                    if (blnAdd) {
                        $('.resource-booking-backdrop-layer').remove();
                        let backdrop = '<div class="resource-booking-backdrop-layer show"></div>';
                        $("body").append(backdrop);
                    } else {
                        window.setTimeout(function () {
                            $('.resource-booking-backdrop-layer').remove();
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
                initializeIdleDetector: function initializeIdleDetector(idleAfter) {
                    let self = this;
                    $(document).idle({
                        onIdle: function onIdle() {
                            self.isIdle = true;
                        },
                        onActive: function () {
                            self.isIdle = false;
                            self.fetchDataRequest();
                        },
                        idle: idleAfter,
                    });
                }

            }
        });
    }
}



