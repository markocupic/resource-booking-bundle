/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
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
                    // The settings below are optional
                    audio: {
                        notifyOnNewBookingsAudio: 'bundles/markocupicresourcebooking/audio/bell.mp3'
                    },
                    enableAudio: true,
                    autocloseWindowsAfter: 2500,
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
                autoCloseBookingWindowTimeout: null,
                messages: null,
                // Indicates if user is idle
                isIdle: false,
                // Do not run fetchDataRequest() if there is a pending request
                isBusy: false,
            },

            created: function created() {

                // Detect unsupported browsers
                let ua = window.navigator.userAgent;
                let msie = ua.indexOf('MSIE ');
                if (msie > 0) {
                    alert('This plugin is not compatible with your browser. Please use a current browser (like Opera, Firefox, Safari or Google Chrome), that is not out of date.')
                }

                // Override defaults
                this.options = {...this.options, ...options}

                // Show the loading spinner for 2s
                window.setTimeout(() => {
                    this.fetchDataRequest();
                }, 2000);

                // Fetch data from server each 15s
                this.intervals.fetchDataRequest = window.setInterval(() => {
                    if (!this.isIdle && !this.isBusy) {
                        this.fetchDataRequest();
                    }
                }, 15000);

                // Initialize idle detector
                // Idle after 5 min (300000 ms)
                let idleAfter = 300000;
                window.setTimeout(() => {
                    this.initializeIdleDetector(document, idleAfter);
                }, 10000);

                document.addEventListener('keyup', evt => {
                    if (evt.keyCode === 27 && this.mode === 'booking-window') {
                        this.hideBookingWindow();
                    }
                });
            },

            // Watchers
            watch: {
                // Watcher
                isReady: function isReady(newVal, oldVal) {
                    //
                },
                activeResourceTypeId: function activeResourceTypeId(newVal, oldVal) {
                    this.applyFilterRequest(newVal, this.activeResourceId, this.activeWeekTstamp);
                },
                activeResourceId: function activeResourceId(newVal, oldVal) {
                    this.applyFilterRequest(this.activeResourceTypeId, newVal, this.activeWeekTstamp);
                },
                activeWeekTstamp: function activeWeekTstamp(newVal, oldVal) {
                    this.applyFilterRequest(this.activeResourceTypeId, this.activeResourceId, newVal);
                },
                rows: function (newVal, oldVal) {
                    if (newVal.length === 0 || oldVal.length === 0) {
                        return;
                    }

                    let newBooking = false;

                    Object.keys(newVal).forEach(i => {
                        Object.keys(newVal[i]['cellData']).forEach(ii => {
                            if (newVal[i]['cellData'][ii]['isBooked'] === true && oldVal[i]['cellData'][ii]['isBooked'] === false) {
                                if (newVal[i]['cellData'][ii]['mondayTimestampSelectedWeek'] === oldVal[i]['cellData'][ii]['mondayTimestampSelectedWeek']) {
                                    if (newVal[i]['cellData'][ii]['resourceId'] === oldVal[i]['cellData'][ii]['resourceId']) {
                                        newBooking = true;
                                    }
                                }
                            }
                        });
                    });

                    if (newBooking === true) {
                        if (this.options.enableAudio) {
                            this.playAudio(this.options.audio.notifyOnNewBookingsAudio);
                        }
                    }
                }
            },

            methods: {

                /**
                 * Fetch all the data from the server
                 */
                fetchDataRequest: function fetchDataRequest() {

                    let action = 'fetchDataRequest';
                    this.isBusy = true;

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', this.options.requestToken);
                    data.append('action', action);
                    data.append('moduleKey', this.options.moduleKey);

                    // Fetch
                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                    .then(res => {
                        this.checkResponse(res);
                        return res.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            for (let key in response['data']) {
                                this[key] = response['data'][key];
                            }
                        }
                        return response;
                    })
                    .then(response => {
                        this.isReady = true;
                        this.isBusy = false;
                    })
                    .catch(error => {
                        this.isReady = false;
                        this.isBusy = false;
                    });
                },

                /**
                 * Apply the filter changes
                 */
                applyFilterRequest: function applyFilterRequest(activeResourceTypeId, activeResourceId, activeWeekTstamp) {

                    let action = 'applyFilterRequest';
                    this.isBusy = true;

                    this.toggleBackdrop(true);

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', this.options.requestToken);
                    data.append('action', action);
                    data.append('resType', activeResourceTypeId);
                    data.append('res', activeResourceId);
                    data.append('date', activeWeekTstamp);
                    data.append('moduleKey', this.options.moduleKey);

                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                    .then(res => {
                        this.checkResponse(res);
                        return res.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            Object.keys(response.data).forEach(key => {
                                this[key] = response.data[key];
                            });
                        }
                        return response;
                    })
                    .then(response => {
                        this.toggleBackdrop(false);
                        this.isBusy = false;
                    })
                    .catch(response => {
                        this.toggleBackdrop(false);
                        this.isBusy = false;
                    });
                },

                /**
                 * Send booking request
                 */
                bookingRequest: function bookingRequest() {

                    let action = 'bookingRequest';

                    let form = this.$el.querySelector('.booking-window form');
                    if (!form) {
                        console.error('Form not found');
                    }

                    let data = new FormData(form);
                    data.append('REQUEST_TOKEN', this.options.requestToken);
                    data.append('action', action);
                    data.append('resourceId', this.bookingWindow.activeTimeSlot.resourceId);
                    //data.append('description', this.$el.querySelectorAll('.booking-window [name="bookingDescription"]')[0].value);
                    //data.append('bookingRepeatStopWeekTstamp', this.$el.querySelectorAll('.booking-repeat-stop-week-tstamp')[0].value);
                    data.append('moduleKey', this.options.moduleKey);
                    //data.append('itemsBooked', this.$el.querySelector('[name="itemsBooked"]') ? this.$el.querySelector('[name="itemsBooked"]').value : '1');

                    Object.keys(this.bookingWindow.selectedTimeSlots).forEach(key => {
                        data.append('bookingDateSelection[]', this.bookingWindow.selectedTimeSlots[key]);
                    });

                    // Call onBeforeBookingRequest callback
                    if (this.options.callbacks.onBeforeBookingRequest.call(this, data) === true) {
                        fetch(window.location.href,
                            {
                                method: "POST",
                                body: data,
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest'
                                },
                            })
                        .then(res => {
                            this.checkResponse(res);
                            return res.json();
                        })
                        .then(response => {
                            if (response.status === 'success') {
                                this.bookingWindow.messages = response.data.messages;
                                this.autoCloseBookingWindowTimeout = window.setTimeout(() => {
                                    this.mode = 'main-window';
                                }, this.options.autocloseWindowsAfter);
                            } else {
                                this.bookingWindow.messages = response.data.messages;
                            }
                            // Always
                            this.bookingWindow.showConfirmationMsg = true;
                            this.fetchDataRequest();
                        })
                        .then(response => {
                            // Call onAfterBookingRequest callback
                            this.options.callbacks.onAfterBookingRequest.call(this, data);
                        })
                        .catch(response => {
                            this.isReady = false;
                            // Always
                            this.bookingWindow.showConfirmationMsg = true;
                            this.fetchDataRequest();
                        });
                    }
                },

                /**
                 * Send resource availability request
                 */
                bookingFormValidationRequest: function bookingFormValidationRequest() {

                    let action = 'bookingFormValidationRequest';

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', this.options.requestToken);
                    data.append('action', action);
                    data.append('resourceId', this.bookingWindow.activeTimeSlot.resourceId);
                    data.append('bookingRepeatStopWeekTstamp', this.$el.querySelectorAll('.booking-repeat-stop-week-tstamp')[0].value);
                    data.append('moduleKey', this.options.moduleKey);
                    data.append('itemsBooked', this.$el.querySelector('[name="itemsBooked"].booking-repeat-stop-week-tstamp') ? this.$el.querySelector('[name="itemsBooked"].booking-repeat-stop-week-tstamp').value : '1');

                    Object.keys(this.bookingWindow.selectedTimeSlots).forEach(key => {
                        data.append('bookingDateSelection[]', this.bookingWindow.selectedTimeSlots[key]);
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
                        this.checkResponse(res);
                        return res.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            this.bookingFormValidation = response.data;
                            this.isReady = true;
                        }
                    })
                    .catch(response => {
                        this.isReady = false;
                    });
                },

                /**
                 * Send cancel booking request
                 */
                cancelBookingRequest: function cancelBookingRequest() {

                    let action = 'cancelBookingRequest';

                    let data = new FormData();
                    data.append('REQUEST_TOKEN', this.options.requestToken);
                    data.append('action', action);
                    data.append('bookingId', this.bookingWindow.booking.id);
                    data.append('deleteBookingsWithSameBookingUuid', this.bookingWindow.deleteBookingsWithSameBookingUuid);
                    data.append('moduleKey', this.options.moduleKey);

                    fetch(window.location.href, {
                        method: "POST",
                        body: data,
                        headers: {
                            'x-requested-with': 'XMLHttpRequest'
                        },
                    })
                    .then(res => {
                        this.checkResponse(res);
                        return res.json();
                    })
                    .then(response => {
                        if (response.status === 'success') {
                            this.bookingWindow.messages = response.data.messages;
                            this.autoCloseBookingWindowTimeout = window.setTimeout(() => {
                                this.mode = 'main-window';
                            }, this.options.autocloseWindowsAfter);
                        } else {
                            this.bookingWindow.messages = response.data.messages;
                        }
                        // Always
                        this.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                        this.bookingWindow.showConfirmationMsg = true;
                        this.fetchDataRequest();
                    })
                    .catch(response => {
                        this.isReady = false;
                        // Always
                        this.bookingWindow.showConfirmationMsg = true;
                        this.fetchDataRequest();
                        this.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                    });
                },

                /**
                 * Jump to next/previous week
                 * @param tstamp
                 * @param evt
                 */
                jumpWeekRequest: function jumpWeekRequest(tstamp, evt) {

                    evt.preventDefault();
                    evt.stopPropagation();

                    if (this.isBusy) {
                        return false;
                    }

                    // Prevent bubbling invalid requests
                    if (tstamp === this.activeWeekTstamp || tstamp < this.filterBoard.tstampFirstPossibleWeek || tstamp > this.filterBoard.tstampLastPossibleWeek) {
                        return false;
                    }

                    // Vue watcher will trigger this.applyFilterRequest()
                    this.activeWeekTstamp = tstamp;
                },

                /**
                 *
                 * @param objActiveTimeSlot
                 * @param booking
                 * @param action
                 */
                openBookingWindow: function openBookingWindow(objActiveTimeSlot, action, booking = null) {

                    this.mode = 'booking-window';

                    this.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                    this.bookingWindow.selectedTimeSlots = [];
                    this.bookingWindow.action = action;
                    this.bookingWindow.showConfirmationMsg = false;
                    this.bookingWindow.activeTimeSlot = objActiveTimeSlot;
                    this.bookingWindow.booking = booking;

                    this.bookingWindow.messages = {
                        confirmation: null,
                        info: null,
                        error: null,
                    };
                    this.bookingWindow.selectedTimeSlots.push(objActiveTimeSlot.bookingCheckboxValue);
                    this.bookingFormValidation = [];

                    if (action === 'showBookingForm') {
                        window.setTimeout(() => {
                            this.bookingFormValidationRequest();
                        }, 500);
                    }

                    // Wrap this code, otherwise querySelector will not find dom elements
                    window.setTimeout(() => {
                        let inputBookingDescription = this.$el.querySelector('.booking-window input[name="bookingDescription"]');
                        if (inputBookingDescription !== null) {
                            inputBookingDescription.setAttribute('value', '');
                        }

                        let weekRepeatOptions = this.$el.querySelectorAll('.booking-window .booking-repeat-stop-week-tstamp option');
                        if (weekRepeatOptions.length > 0) {
                            weekRepeatOptions.forEach(elOption => elOption.removeAttribute('selected'));
                        }
                    }, 20);
                },

                /**
                 * Hide booking window
                 */
                hideBookingWindow: function hideBookingWindow() {
                    clearTimeout(this.autoCloseBookingWindowTimeout);
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

                    $(document).idle({
                        onIdle: () => {
                            this.isIdle = true;
                        },
                        onActive: () => {
                            this.isIdle = false;
                            this.fetchDataRequest();
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

                    let idleSecondsCounter = idleTimeout;
                    let listenerType = ['keydown', 'mousemove', 'mousedown', 'touchstart'];

                    listenerType.forEach(type => {

                        el.addEventListener(type, () => {
                            if (this.isIdle) {
                                // On active again
                                this.isIdle = false;
                                this.fetchDataRequest();
                            }

                            idleSecondsCounter = idleTimeout;
                        }, false);
                    });

                    window.setInterval(() => {
                        if (this.isIdle) {
                            return;
                        }

                        idleSecondsCounter -= 1000;
                        if (idleSecondsCounter <= 0) {
                            // On idle
                            this.isIdle = true;
                        }
                    }, 1000);
                },

                /**
                 * Play audio file
                 * @param src
                 */
                playAudio: function playAudio(src) {

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



