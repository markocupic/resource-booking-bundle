/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2021
 * @link https://github.com/markocupic/resource-booking-bundle
 */
"use strict";

class resourceBookingApp {
    constructor(vueElement, options) {
        new Vue({
            el: vueElement,
            data: {
                // Module options
                options: {
                    requestToken: '',
                    moduleKey: '',
                    audio: {
                        notifyOnNewBookingsAudio: 'bundles/markocupicresourcebooking/audio/bell.mp3'
                    },
                    enableAudio: true,
                    // Callback functions
                    callbacks: {
                        // Callback function to be executed before booking request is fired
                        onBeforeBookingRequest: objFormData => {
                            return true;
                        },
                        // Callback function to be executed after booking request was fired
                        onAfterBookingRequest: () => {
                        },
                    },
                },
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

                // Override defaults
                self.options = {...self.options, ...options}

                // Show the loading spinner for 2s
                window.setTimeout(() => {
                    self.fetchDataRequest();
                }, 2000);

                // Fetch data from server each 15s
                self.intervals.fetchDataRequest = window.setInterval(() => {
                    if (!self.isIdle && !self.isBusy) {
                        self.fetchDataRequest();
                    }
                }, 15000);

                // Initialize idle detector
                // Idle after 5 min (300000 ms)
                let idleAfter = 300000;
                window.setTimeout(() => {
                    self.initializeIdleDetector(document, idleAfter);
                }, 10000);

                document.addEventListener('keyup', evt => {
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
                },
                rows: function (newObj, oldObj) {
                    let self = this;

                    if (newObj.length === 0 || oldObj.length === 0) {
                        return;
                    }

                    let newBooking = false;

                    Object.keys(newObj).forEach(i => {
                        Object.keys(newObj[i]['cellData']).forEach(ii => {
                            if (newObj[i]['cellData'][ii]['isBooked'] === true && oldObj[i]['cellData'][ii]['isBooked'] === false) {
                                if (newObj[i]['cellData'][ii]['mondayTimestampSelectedWeek'] === oldObj[i]['cellData'][ii]['mondayTimestampSelectedWeek']) {
                                    if (newObj[i]['cellData'][ii]['resourceId'] === oldObj[i]['cellData'][ii]['resourceId']) {
                                        newBooking = true;
                                    }
                                }
                            }
                        });
                    });

                    if (newBooking === true) {
                        if (self.options.enableAudio) {
                            self.playAudio(self.options.audio.notifyOnNewBookingsAudio);
                        }
                    }
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
                    data.append('REQUEST_TOKEN', self.options.requestToken);
                    data.append('action', action);
                    data.append('moduleKey', self.options.moduleKey);

                    // Fetch
                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                    .then(res => {
                        self.checkResponse(res);
                        return res.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            for (let key in response['data']) {
                                self[key] = response['data'][key];
                            }
                        }
                        return response;
                    })
                    .then(response => {
                        self.isReady = true;
                        self.isBusy = false;
                    })
                    .catch(error => {
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
                    data.append('REQUEST_TOKEN', self.options.requestToken);
                    data.append('action', action);
                    data.append('resType', activeResourceTypeId);
                    data.append('res', activeResourceId);
                    data.append('date', activeWeekTstamp);
                    data.append('moduleKey', self.options.moduleKey);

                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                    .then(res => {
                        self.checkResponse(res);
                        return res.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            Object.keys(response.data).forEach(key => {
                                self[key] = response.data[key];
                            });
                        }
                        return response;
                    })
                    .then(response => {
                        self.toggleBackdrop(false);
                        self.isBusy = false;
                    })
                    .catch(response => {
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

                    let form = self.$el.querySelector('.booking-window form');
                    if (!form) {
                        console.error('Form not found');
                    }

                    let data = new FormData(form);
                    data.append('REQUEST_TOKEN', self.options.requestToken);
                    data.append('action', action);
                    data.append('resourceId', self.bookingWindow.activeTimeSlot.resourceId);
                    data.append('description', self.$el.querySelectorAll('.booking-window [name="bookingDescription"]')[0].value);
                    data.append('bookingRepeatStopWeekTstamp', self.$el.querySelectorAll('.booking-repeat-stop-week-tstamp')[0].value);
                    data.append('moduleKey', self.options.moduleKey);

                    Object.keys(self.bookingWindow.selectedTimeSlots).forEach(key => {
                        data.append('bookingDateSelection[]', self.bookingWindow.selectedTimeSlots[key]);
                    });

                    // Call onBeforeBookingRequest callback
                    if (self.options.callbacks.onBeforeBookingRequest.call(self, data) === true) {
                        fetch(window.location.href,
                            {
                                method: "POST",
                                body: data,
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest'
                                },
                            })
                        .then(res => {
                            self.checkResponse(res);
                            return res.json();
                        })
                        .then(response => {
                            if (response.status === 'success') {
                                self.bookingWindow.messages = response.data.messages;
                                window.setTimeout(() => {
                                    self.mode = 'main-window';
                                }, 2500);
                            } else {
                                self.bookingWindow.messages = response.data.messages;
                            }
                            // Always
                            self.bookingWindow.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        })
                        .then(response => {
                            // Call onAfterBookingRequest callback
                            self.options.callbacks.onAfterBookingRequest.call(self, data);
                        })
                        .catch(response => {
                            self.isReady = false;
                            // Always
                            self.bookingWindow.showConfirmationMsg = true;
                            self.fetchDataRequest();
                        });
                    }
                },

                /**
                 * Send resource availability request
                 */
                bookingFormValidationRequest: function bookingFormValidationRequest() {
                    let self = this;
                    let action = 'bookingFormValidationRequest';

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', self.options.requestToken);
                    data.append('action', action);
                    data.append('resourceId', self.bookingWindow.activeTimeSlot.resourceId);
                    data.append('bookingRepeatStopWeekTstamp', self.$el.querySelectorAll('.booking-repeat-stop-week-tstamp')[0].value);
                    data.append('moduleKey', self.options.moduleKey);

                    Object.keys(self.bookingWindow.selectedTimeSlots).forEach(key => {
                        data.append('bookingDateSelection[]', self.bookingWindow.selectedTimeSlots[key]);
                    });

                    fetch(window.location.href,
                        {
                            method: "POST",
                            body: data,
                            headers: {
                                'x-requested-with': 'XMLHttpRequest'
                            },
                        })
                    .then(res => {
                        self.checkResponse(res);
                        return res.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            self.bookingFormValidation = response.data;
                            self.isReady = true;
                        }
                    })
                    .catch(response => {
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
                    data.append('REQUEST_TOKEN', self.options.requestToken);
                    data.append('action', action);
                    data.append('bookingId', self.bookingWindow.activeTimeSlot.bookingId);
                    data.append('deleteBookingsWithSameBookingUuid', self.bookingWindow.deleteBookingsWithSameBookingUuid);
                    data.append('moduleKey', self.options.moduleKey);

                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                    .then(res => {
                        self.checkResponse(res);
                        return res.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            self.bookingWindow.messages = response.data.messages;
                            window.setTimeout(() => {
                                self.mode = 'main-window';
                            }, 2500);
                        } else {
                            self.bookingWindow.messages = response.data.messages;
                        }
                        // Always
                        self.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                        self.bookingWindow.showConfirmationMsg = true;
                        self.fetchDataRequest();
                    })
                    .catch(response => {
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
                 * @param evt
                 */
                jumpWeekRequest: function jumpWeekRequest(tstamp, evt) {

                    let self = this;
                    evt.preventDefault();
                    evt.stopPropagation();

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
                openBookingWindow: function openBookingWindow (objActiveTimeSlot, action) {
                    let self = this;

                    self.mode = 'booking-window';

                    self.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                    self.bookingWindow.selectedTimeSlots = [];
                    self.bookingWindow.action = action;
                    self.bookingWindow.showConfirmationMsg = false;
                    self.bookingWindow.activeTimeSlot = objActiveTimeSlot;
                    self.bookingWindow.messages = {
                        confirmation: null,
                        info: null,
                        error: null,
                    };
                    self.bookingWindow.selectedTimeSlots.push(objActiveTimeSlot.bookingCheckboxValue);
                    self.bookingFormValidation = [];

                    if (action === 'showBookingForm') {
                        window.setTimeout(() => {
                            self.bookingFormValidationRequest();
                        }, 500);
                    }

                    // Wrap this code, otherwise querySelector will not find dom elements
                    window.setTimeout(() => {
                        let inputBookingDescription = self.$el.querySelector('.booking-window input[name="bookingDescription"]');
                        if (inputBookingDescription !== null) {
                            inputBookingDescription.setAttribute('value', '');
                        }

                        let weekRepeatOptions = self.$el.querySelectorAll('.booking-window .booking-repeat-stop-week-tstamp option');
                        if (weekRepeatOptions.length > 0) {
                            weekRepeatOptions.forEach(elOption => elOption.removeAttribute('selected'));
                        }
                    }, 20);
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
                        // Remove backdrop layer
                        let backDrops = document.querySelectorAll('.resource-booking-backdrop-layer');
                        if (backDrops.length > 0) {
                            backDrops.forEach(backDrop => backDrop.parentNode.removeChild(backDrop));
                        }

                        // Add backdrop layer
                        let backDrop = document.createElement("div");
                        backDrop.classList.add("resource-booking-backdrop-layer");
                        backDrop.classList.add("show");
                        document.querySelector('body').append(backDrop);

                    } else {
                        // Remove backdrop layer
                        window.setTimeout(() => {
                            let backDrops = document.querySelectorAll('.resource-booking-backdrop-layer');
                            if (backDrops.length > 0) {
                                backDrops.forEach(backDrop => backDrop.parentNode.removeChild(backDrop));
                            }
                        }, 100);
                    }
                },

                /**
                 * Check json response
                 * @param res
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
                initializeIdleDetectorOld: function initializeIdleDetectorOld(idleAfter) {
                    let self = this;
                    $(document).idle({
                        onIdle:  () => {
                            self.isIdle = true;
                        },
                        onActive: () => {
                            self.isIdle = false;
                            self.fetchDataRequest();
                        },
                        idle: idleAfter,
                    });
                },

                /**
                 * Initialize idle detector
                 * @param el
                 * @param idleTimeout
                 */
                initializeIdleDetector: function initializeIdleDetector(el, idleTimeout) {
                    let self = this;
                    let idleSecondsCounter = idleTimeout;
                    let listenerType = ['keydown', 'mousemove', 'mousedown', 'touchstart'];

                    listenerType.forEach(type => {

                        el.addEventListener(type, () => {
                            if (self.isIdle) {
                                // On active again
                                self.isIdle = false;
                                self.fetchDataRequest();
                            }

                            idleSecondsCounter = idleTimeout;
                        }, false);
                    });

                    window.setInterval(() => {
                        if (self.isIdle) {
                            return;
                        }

                        idleSecondsCounter -= 1000;
                        if (idleSecondsCounter <= 0) {
                            // On idle
                            self.isIdle = true;
                        }
                    }, 1000);
                },

                /**
                 * Play audio file
                 * @param src
                 */
                playAudio: function playAudio(src) {
                    let self = this;
                    let audio = new Audio(src);
                    audio.setAttribute('id', 'rbbBellRingtone');
                    if (document.querySelector('body audio') === null) {
                        document.querySelector('body').appendChild(audio);
                    }
                    audio.play();
                }
            }
        });
    }
}



