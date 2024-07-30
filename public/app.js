// noinspection ExceptionCaughtLocallyJS

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

"use strict";
if (typeof ResourceBookingApp !== 'function') {

    window.ResourceBookingApp = class {
        constructor(elId, opt) {
            const {createApp} = Vue

            // Instantiate vue.js application
            const app = createApp({
                data() {
                    return {
                        // Module options
                        options: {
                            requestToken: '',
                            moduleKey: '',
                            // The settings below are optional
                            audio: {
                                notifyOnNewBookingsAudio: 'bundles/markocupicresourcebooking/audio/booking-alert.mp3'
                            },
                            enableAudio: true,
                            autocloseWindowsAfter: 2500,
                        },
                        // indicates if application is initialized, switches to true, when refreshData request was fired first time
                        // and the request status is 200
                        isReady: false,
                        // Indicate the mode
                        mode: 'week-calendar',
                        // Indicates the last response code
                        lastResponseCode: 200,
                        // Contains data about available resource types, resources and weeks (week selector)
                        filterBoard: null,
                        // Indicates if the current user hass logged in as a frontend user
                        hasLoggedInUser: false,
                        // Contains the logged-in user data
                        loggedInUser: [],
                        // Contains the weekdays
                        weekdays: [],
                        // Contains the time slots (first col in the booking table)
                        timeSlots: [],
                        // The cell data of each row in the booking table
                        rows: [],
                        // Contains the id
                        activeResourceTypeId: 'undefined',
                        // Contains the data in an array: id, title, etc.
                        activeResourceType: [],
                        // Contains the id
                        activeResourceId: 'undefined',
                        // Contains the data in an array: id, title, etc.
                        activeResource: [],
                        // Beginn week weekday of current week 00:00 UTC
                        activeWeekTstamp: 0,
                        // Contains data about the active week: tstampStart, tstampEnd, dateStart, dateEnd, weekNumber, year
                        activeWeek: [],
                        bookingRepeatsSelection: [],
                        bookingWindow: {
                            action: null,
                            activeTimeSlot: null,
                            booking: null,
                            response: {},
                            deleteBookingsWithSameBookingUuid: false,
                            selectedTimeSlots: [],
                            showCancelBookingForm: false,
                            showCancelBookingButton: false,
                        },
                        intervals: [],
                        autoCloseBookingWindowTimeout: null,
                        messages: ['confirm'],
                        // Indicates if user is idle
                        isIdle: false,
                        // Queue the requests
                        requestQueue: [],
                        // true if there is a pending request
                        isBusy: false,
                    }
                },

                created() {

                    // Detect unsupported browsers
                    let ua = window.navigator.userAgent;
                    let msie = ua.indexOf('MSIE ');
                    if (msie > 0) {
                        alert('This plugin is not compatible with your browser. Please use a current browser (like Opera, Firefox, Safari or Google Chrome), that is not out of date.')
                    }

                    // Override defaults
                    this.options = {...this.options, ...opt}

                    // Show the loading spinner for 2s
                    window.setTimeout(() => {
                        this.refreshDataRequest(true);
                    }, 500);

                    // Fetch data from server each 15s
                    this.intervals.refreshDataRequest = window.setInterval(() => {
                        if (!this.isIdle) {
                            this.refreshDataRequest();
                        }
                    }, 15000);

                    // Use a queue/stack to store the requests.
                    // The last item (request) in the stack will be fired and
                    // the items below will be deleted.
                    // Do this each 200 ms
                    // In this way we can minimize the number of unnecessary requests.
                    this.intervals.applyFilterRequest = window.setInterval(() => {
                        if (this.isBusy) {
                            return;
                        }

                        let length = this.requestQueue.length;

                        if (length === 0) {
                            return;
                        }

                        if (length > 5) {
                            let delItems = length - 3;
                            this.requestQueue.splice(0, delItems);
                            length = this.requestQueue.length;
                        }

                        let current = this.requestQueue[length - 1];
                        this.requestQueue.splice(0, length);
                        this.applyFilterRequest(...current);
                    }, 200);

                    // Initialize idle detector
                    // Idle after 5 min (300000 ms)
                    let idleAfter = 300000;
                    window.setTimeout(() => {
                        this.initializeIdleDetector(document, idleAfter);
                    }, 10000);

                    document.addEventListener('keyup', evt => {
                        if (evt.code === 'Escape' && this.mode === 'booking-window') {
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
                        //this.applyFilterRequest(newVal, this.activeResourceId, this.activeWeekTstamp);
                        this.requestQueue.push([newVal, this.activeResourceId, this.activeWeekTstamp]);
                    },
                    activeResourceId: function activeResourceId(newVal, oldVal) {
                        //this.applyFilterRequest(this.activeResourceTypeId, newVal, this.activeWeekTstamp);
                        this.requestQueue.push([this.activeResourceTypeId, newVal, this.activeWeekTstamp]);
                    },
                    activeWeekTstamp: function activeWeekTstamp(newVal, oldVal) {
                        this.requestQueue.push([this.activeResourceTypeId, this.activeResourceId, newVal]);
                        //this.applyFilterRequest(this.activeResourceTypeId, this.activeResourceId, newVal);
                    },
                    rows: async function (newVal, oldVal) {

                        if (newVal.length === 0 || oldVal.length === 0) {
                            return;
                        }

                        let hasNewBooking = false;

                        await (async function () {
                            for await (const rowIndex of Object.keys(newVal)) {

                                for (const colIndex of Object.keys(newVal[rowIndex]['cellData'])) {

                                    if (parseInt(newVal[rowIndex]['cellData'][colIndex]['bookingCount']) > parseInt(oldVal[rowIndex]['cellData'][colIndex]['bookingCount'])) {
                                        if (newVal[rowIndex]['cellData'][colIndex]['beginnWeekTimestampSelectedWeek'] === oldVal[rowIndex]['cellData'][colIndex]['beginnWeekTimestampSelectedWeek']) {
                                            if (newVal[rowIndex]['cellData'][colIndex]['pid'] === oldVal[rowIndex]['cellData'][colIndex]['pid']) {
                                                hasNewBooking = true;
                                            }
                                        }
                                    }
                                }
                            }
                        })();

                        if (hasNewBooking === true) {
                            if (this.options.enableAudio) {
                                this.playAudio(this.options.audio.notifyOnNewBookingsAudio);
                            }
                        }
                    }
                },

                methods: {

                    /**
                     * Fetch data from the server and refresh the booking table.
                     */
                    refreshDataRequest: async function (blnInitial = false) {

                        let action = 'refreshDataRequest';

                        const formData = new FormData();
                        formData.append('REQUEST_TOKEN', this.options.requestToken);
                        formData.append('action', action);
                        formData.append('moduleKey', this.options.moduleKey);

                        try {
                            const response = await fetch(window.location.href, {
                                method: "POST",
                                body: formData,
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest'
                                },
                            });
                            this.lastResponseCode = response.status;

                            if (!response.ok) {
                                // Custom message for failed HTTP codes
                                if (response.status === 404) {
                                    throw new Error('404, Not found');
                                }

                                if (response.status === 500) {
                                    throw new Error('500, internal server error');
                                }

                                // For any other server error
                                throw new Error(response.statusText);
                            }

                            this.checkResponse(response);

                            const json = await response.json();

                            if (json.status === 'success') {
                                for (let key in json['data']) {
                                    // Prevent competing requests
                                    if (key === 'messages') continue;
                                    if (!blnInitial && key === 'activeResourceId') continue;
                                    if (!blnInitial && key === 'activeResource') continue;
                                    if (!blnInitial && key === 'activeResourceTypeId') continue;
                                    if (!blnInitial && key === 'activeResourceType') continue;
                                    if (!blnInitial && key === 'activeWeek') continue;
                                    if (!blnInitial && key === 'activeWeekTstamp') continue;

                                    this[key] = json['data'][key];
                                }
                            }

                            this.isReady = true;

                            return response;
                        } catch (error) {
                            this.isReady = false;
                            console.error('Fetch', error);
                        }
                    },

                    /**
                     * Apply the filter changes
                     */
                    applyFilterRequest: async function (activeResourceTypeId, activeResourceId, activeWeekTstamp) {

                        this.deleteMessages();

                        this.isBusy = true;
                        let action = 'applyFilterRequest';

                        const formData = new FormData();
                        formData.append('REQUEST_TOKEN', this.options.requestToken);
                        formData.append('action', action);
                        formData.append('resType', activeResourceTypeId);
                        formData.append('res', activeResourceId);
                        formData.append('date', activeWeekTstamp);
                        formData.append('moduleKey', this.options.moduleKey);

                        try {
                            const response = await fetch(window.location.href, {
                                method: "POST",
                                body: formData,
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest'
                                },
                            });
                            this.lastResponseCode = response.status;

                            if (!response.ok) {
                                // Custom message for failed HTTP codes
                                if (response.status === 404) {
                                    throw new Error('404, Not found');
                                }

                                if (response.status === 500) {
                                    throw new Error('500, internal server error');
                                }

                                // For any other server error
                                throw new Error(response.statusText);
                            }

                            this.checkResponse(response);

                            const json = await response.json();

                            if (json.status === 'success') {
                                if (this.activeWeekTstamp && parseInt(this.activeWeekTstamp) !== parseInt(json.data['activeWeekTstamp'])) {
                                    this.isBusy = false;
                                    return;
                                }

                                this.messages = json.messages ? json.messages : [];

                                for (const key of Object.keys(json.data)) {
                                    this[key] = json.data[key];
                                }
                            }
                        } catch (error) {
                            console.error('Fetch', error);
                        }
                        this.isBusy = false;
                    },

                    /**
                     * Send booking request
                     */
                    bookingRequest: async function () {
                        this.deleteMessages();
                        let action = 'bookingRequest';
                        this.isBusy = true;

                        let form = this.$el.querySelector('.rbb-js-booking-form');

                        if (!form) {
                            console.error('Form not found');
                        }

                        const formData = new FormData(form);
                        formData.append('REQUEST_TOKEN', this.options.requestToken);
                        formData.append('action', action);
                        formData.append('resourceId', this.bookingWindow.activeTimeSlot.pid);
                        formData.append('moduleKey', this.options.moduleKey);

                        for (const selectedTimeSlot of this.bookingWindow.selectedTimeSlots) {
                            formData.append('bookingDateSelection[]', selectedTimeSlot);
                        }

                        const event = new CustomEvent('rbb_before_booking_request', {
                            detail: {
                                'instance': this,
                                'formData': formData,
                                'blnSend': true,
                            }
                        });

                        await (() => {
                            document.dispatchEvent(event);
                        })();

                        if (event.detail.blnSend === true) {
                            try {
                                const response = await fetch(window.location.href,
                                    {
                                        method: "POST",
                                        body: formData,
                                        headers: {
                                            'x-requested-with': 'XMLHttpRequest'
                                        },
                                    });
                                this.lastResponseCode = response.status;

                                if (!response.ok) {
                                    // Custom message for failed HTTP codes
                                    if (response.status === 404) {
                                        throw new Error('404, Not found');
                                    }

                                    if (response.status === 500) {
                                        throw new Error('500, internal server error');
                                    }

                                    // For any other server error
                                    throw new Error(response.statusText);
                                }

                                this.checkResponse(response);

                                const json = await response.json();

                                this.bookingWindow.response = json.data;
                                this.messages = json.messages ? json.messages : [];

                                if (json.status === 'success') {
                                    this.autoCloseBookingWindowTimeout = window.setTimeout(() => {
                                        this.hideBookingWindow();
                                    }, this.options.autocloseWindowsAfter);

                                    const event = new CustomEvent('rbb_after_booking_request', {
                                        detail: {
                                            'instance': this,
                                            'formData': formData,
                                            'response': response,
                                        }
                                    });

                                    await this.refreshDataRequest();

                                    await (() => {
                                        document.dispatchEvent(event);
                                    })();
                                }
                            } catch (error) {
                                this.isReady = false;
                                console.error('Fetch', error);

                                // Always
                                await this.refreshDataRequest();
                            }
                        }
                        this.isBusy = false;
                    },

                    /**
                     * Send resource availability request
                     */
                    bookingFormValidationRequest: async function () {
                        this.deleteMessages();
                        const action = 'bookingFormValidationRequest';
                        this.isBusy = true;

                        const formData = new FormData();
                        formData.append('REQUEST_TOKEN', this.options.requestToken);
                        formData.append('action', action);
                        formData.append('resourceId', this.bookingWindow.activeTimeSlot.pid);
                        formData.append('bookingRepeatStopWeekTstamp', this.$el.querySelector('.rbb-js-booking-repeat-stop-week-tstamp').value);
                        formData.append('moduleKey', this.options.moduleKey);
                        formData.append('itemsBooked', this.$el.querySelector('[name="itemsBooked"]') ? this.$el.querySelector('[name="itemsBooked"]').value : '1');

                        for (const selectedTimeSlot of this.bookingWindow.selectedTimeSlots) {
                            formData.append('bookingDateSelection[]', selectedTimeSlot);
                        }

                        try {
                            const response = await fetch(window.location.href,
                                {
                                    method: "POST",
                                    body: formData,
                                    headers: {
                                        'x-requested-with': 'XMLHttpRequest'
                                    }
                                });

                            this.lastResponseCode = response.status;

                            if (!response.ok) {
                                // Custom message for failed HTTP codes
                                if (response.status === 404) {
                                    throw new Error('404, Not found');
                                }

                                if (response.status === 500) {
                                    throw new Error('500, internal server error');
                                }

                                // For any other server error
                                throw new Error(response.statusText);
                            }

                            await this.checkResponse(response);

                            const json = await response.json();

                            if (json.status) {
                                this.messages = json.messages ? json.messages : [];
                                this.bookingWindow.response = json.data;
                            }

                            if (json.status === 'success') {
                                this.isReady = true;
                            }
                        } catch (error) {
                            this.isReady = false;
                            console.error('Fetch', error);
                        }
                        this.isBusy = false;
                    },

                    /**
                     * Send cancel booking request
                     */
                    cancelBookingRequest: async function () {
                        this.deleteMessages();
                        let action = 'cancelBookingRequest';

                        this.bookingWindow.showCancelBookingForm = false;
                        this.bookingWindow.showCancelBookingButton = false;

                        const formData = new FormData();
                        formData.append('REQUEST_TOKEN', this.options.requestToken);
                        formData.append('action', action);
                        formData.append('id', this.bookingWindow.booking.id);
                        formData.append('deleteBookingsWithSameBookingUuid', this.bookingWindow.deleteBookingsWithSameBookingUuid);
                        formData.append('moduleKey', this.options.moduleKey);

                        try {
                            const response = await fetch(window.location.href, {
                                method: "POST",
                                body: formData,
                                headers: {
                                    'x-requested-with': 'XMLHttpRequest'
                                },
                            });

                            this.lastResponseCode = response.status;

                            if (!response.ok) {
                                // Custom message for failed HTTP codes
                                if (response.status === 404) {
                                    throw new Error('404, Not found');
                                }

                                if (response.status === 500) {
                                    throw new Error('500, internal server error');
                                }

                                // For any other server error
                                throw new Error(response.statusText);
                            }

                            this.checkResponse(response);

                            const json = await response.json();

                            this.bookingWindow.response = json.data;

                            if (json.status === 'success') {
                                this.autoCloseBookingWindowTimeout = window.setTimeout(() => {
                                    this.hideBookingWindow();
                                }, this.options.autocloseWindowsAfter);
                            }

                            // Always
                            this.messages = json.messages ? json.messages : [];
                            this.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                            await this.refreshDataRequest();

                        } catch (error) {
                            this.isReady = false;
                            console.error("There was en error: " + error);

                            // Always
                            this.refreshDataRequest();
                            this.bookingWindow.deleteBookingsWithSameBookingUuid = false;
                        }
                    },

                    /**
                     * Jump to next/previous week
                     * @param tstamp
                     * @param evt
                     */
                    jumpWeekRequest: function (tstamp, evt) {

                        this.deleteMessages();

                        evt.preventDefault();
                        evt.stopPropagation();

                        // Prevent bubbling invalid requests
                        if (tstamp === this.activeWeekTstamp || tstamp < this.filterBoard.tstampFirstPermittedWeek || tstamp > this.filterBoard.tstampLastPermittedWeek) {
                            return false;
                        }

                        // Vue watcher will trigger this.applyFilterRequest()
                        this.activeWeekTstamp = tstamp;
                    },

                    /**
                     * @param slot
                     * @param action
                     * @param booking
                     */
                    openBookingWindow: async function (slot, action, booking = null) {
                        this.deleteMessages();
                        this.mode = 'booking-window';
                        this.isBusy = true;

                        // Reset
                        await (() => {
                            this.bookingWindow = {
                                'action': action,
                                'activeTimeSlot': slot,
                                'booking': booking,
                                'response': {},
                                'deleteBookingsWithSameBookingUuid': false,
                                'selectedTimeSlots': [],
                            }
                        })();

                        await (() => {
                            this.bookingWindow.selectedTimeSlots.push(slot.bookingCheckboxValue);
                        })();

                        await (async () => {
                            if (action === 'showBookingForm') {
                                await this.bookingFormValidationRequest();
                            } else if (action === 'showCancelBookingForm') {
                                this.bookingWindow.showCancelBookingButton = true;
                                this.bookingWindow.showCancelBookingForm = true;
                            }
                        })();

                        await (() => {
                            // Wrap this code, otherwise querySelector will not find dom elements
                            let inputBookingDescription = this.$el.querySelector('.rbb-js-booking-form input[name="bookingDescription"]');

                            if (inputBookingDescription !== null) {
                                inputBookingDescription.setAttribute('value', '');
                            }

                            let weekRepeatOptions = this.$el.querySelectorAll('.rbb-js-booking-form .rbb-js-booking-repeat-stop-week-tstamp option');

                            if (weekRepeatOptions.length > 0) {
                                for (const elOption of weekRepeatOptions) {
                                    elOption.removeAttribute('selected')
                                }
                            }
                        })();

                        this.isBusy = false;
                    },

                    /**
                     * Hide booking window
                     */
                    hideBookingWindow: function () {
                        this.deleteMessages();
                        this.mode = 'week-calendar';
                        clearTimeout(this.autoCloseBookingWindowTimeout);
                    },

                    /**
                     * Check json response
                     * @param response
                     */
                    checkResponse: function (response) {
                        this.lastResponseCode = response.status;
                        this.isReady = response.status === 200;
                    },

                    /**
                     * Initialize idle detector
                     * @param el
                     * @param idleTimeout
                     */
                    initializeIdleDetector: function (el, idleTimeout) {

                        let idleSecondsCounter = idleTimeout;
                        let listenerType = ['keydown', 'mousemove', 'mousedown', 'touchstart'];

                        for (const type of listenerType) {
                            el.addEventListener(type, () => {
                                if (this.isIdle) {
                                    // On active again
                                    this.isIdle = false;
                                    this.refreshDataRequest();
                                }

                                idleSecondsCounter = idleTimeout;
                            }, false);
                        }

                        this.intervals.isIdle = window.setInterval(() => {
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
                    playAudio: function (src) {
                        (new Audio(src)).play();
                    },

                    /**
                     * @param type (can be 'error', 'confirm', 'warning', 'info')
                     */
                    getMessage: function (type) {
                        return this.messages[type] ? this.messages[type] : null;
                    },

                    /**
                     * @param type (can be 'error', 'confirm', 'warning', 'info')
                     * @param message
                     */
                    setMessage: function (type, message) {
                        const accepted = ['error', 'confirm', 'warning', 'info'];

                        if (-1 === accepted.indexOf(type)) {
                            alert(`Invalid message type "${type}" detected!`);
                        }

                        this.messages[type] = message;
                    },

                    deleteMessage: function (type) {
                        const accepted = ['error', 'confirm', 'warning', 'info'];

                        if (-1 === accepted.indexOf(type)) {
                            alert(`Invalid message type "${type}" detected!`);
                        }

                        this.messages[type] = null;
                    },

                    deleteMessages: function () {
                        this.messages['error'] = null;
                        this.messages['confirm'] = null;
                        this.messages['warning'] = null;
                        this.messages['info'] = null;
                    }
                }
            });

            app.config.compilerOptions.delimiters = ['[[ ', ' ]]'];
            app.mount(elId);
        }
    }
}
