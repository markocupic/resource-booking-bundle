// noinspection ExceptionCaughtLocallyJS

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
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
                            // Access token of the API: created by the server with every page load
                            accessToken: '',
                            // URLs of the API (WeekCalendarApiController)
                            apiUrls: {
                                calendar: '',
                                validateBooking: '',
                                bookings: '',
                                // Actions of other extensions, "__action__" is replaced by the name of the action
                                action: '',
                            },
                            // Texts for errors detected in the browser (translated in the module template)
                            texts: {
                                networkError: 'The request could not be sent. Please check your internet connection and try again.',
                                accessTokenExpired: 'Please reload the page.',
                                accessDenied: 'You do not have access to this calendar. Please reload the page.',
                            },
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
                        // Begin week weekday of current week 00:00 UTC
                        activeWeekStart: 0,
                        // Contains data about the active week: tstampStart, tstampEnd, dateStart, dateEnd, weekNumber, year
                        activeWeek: [],
                        bookingRepeatsSelection: [],
                        bookingWindow: {
                            action: null,
                            activeTimeSlot: null,
                            booking: null,
                            response: {},
                            deleteRepetitions: false,
                            selectedTimeSlots: [],
                            showCancelBookingForm: false,
                            showCancelBookingButton: false,
                        },
                        intervals: [],
                        messages: ['confirm'],
                        // Indicates if user is idle
                        isIdle: false,
                        // Queue the requests
                        requestQueue: [],
                        // true if there is a pending request
                        isBusy: false,
                        // true if the server could not be reached (network error), see handleRequestError()
                        connectionLost: false,
                        // The API refused the access token (HTTP 401/403): the page has to be reloaded
                        accessLost: false,
                        // Day displayed on small screens (index of the weekday, see weekdays[].index)
                        activeDayIndex: null,
                        // Day displayed in the slot list of the booking window on small screens
                        bookingWindowDayIndex: null,
                        // Messages per form field of the booking form (field name => message)
                        fieldErrors: {},
                        // Start position of a swipe gesture on the week table
                        touchStart: null,
                    }
                },

                created() {

                    // Override defaults
                    this.options = {...this.options, ...opt, texts: {...this.options.texts, ...(opt.texts || {})}}

                    // Show the loading spinner for 2s
                    window.setTimeout(() => {
                        this.refreshDataRequest(true);
                    }, 500);

                    // Fetch data from server each 15s, but not while the tab is hidden or the user is idle
                    this.intervals.refreshDataRequest = window.setInterval(() => {
                        if (!this.isIdle && document.visibilityState !== 'hidden') {
                            this.refreshDataRequest();
                        }
                    }, 15000);

                    // Back in the tab or online again: show the current data right away
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') {
                            this.refreshDataRequest();
                        }
                    });

                    window.addEventListener('online', () => {
                        this.refreshDataRequest();
                    });

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


                },

                // Watchers
                watch: {
                    // Watcher
                    isReady: function isReady(newVal, oldVal) {
                        //
                    },
                    activeResourceTypeId: function activeResourceTypeId(newVal, oldVal) {
                        //this.applyFilterRequest(newVal, this.activeResourceId, this.activeWeekStart);
                        this.requestQueue.push([newVal, this.activeResourceId, this.activeWeekStart]);
                    },
                    activeResourceId: function activeResourceId(newVal, oldVal) {
                        //this.applyFilterRequest(this.activeResourceTypeId, newVal, this.activeWeekStart);
                        this.requestQueue.push([this.activeResourceTypeId, newVal, this.activeWeekStart]);
                    },
                    activeWeekStart: function activeWeekStart(newVal, oldVal) {
                        this.requestQueue.push([this.activeResourceTypeId, this.activeResourceId, newVal]);
                        //this.applyFilterRequest(this.activeResourceTypeId, this.activeResourceId, newVal);
                    },
                    weekdays: function weekdays(newVal, oldVal) {
                        // Keep the selected day while the data is refreshed, otherwise show today (or the first day)
                        if (newVal.some(weekday => weekday.index === this.activeDayIndex)) {
                            return;
                        }

                        const today = new Date().toDateString();
                        const weekdayToday = newVal.find(weekday => weekday.tstamp && new Date(weekday.tstamp * 1000).toDateString() === today);

                        this.activeDayIndex = weekdayToday ? weekdayToday.index : (newVal.length ? newVal[0].index : null);
                    }
                },

                methods: {

                    /**
                     * Fetch data from the server and refresh the booking table.
                     */
                    refreshDataRequest: async function (blnInitial = false) {

                        if (this.accessLost) {
                            return;
                        }

                        const formData = new FormData();

                        if (blnInitial) {
                            // First request of the page: restore the selection of this tab or use the
                            // selection of a direct link. The server validates it and selects a resource
                            // automatically if nothing is selected.
                            formData.append('initial', '1');
                            this.appendAppState(formData, this.getInitialAppState());
                        } else {
                            this.appendAppState(formData);
                        }

                        try {
                            const response = await this.apiFetch('GET', this.options.apiUrls.calendar, formData);

                            if (!response.ok) {
                                await this.throwResponseError(response);
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
                                    if (!blnInitial && key === 'activeWeekStart') continue;

                                    this[key] = json['data'][key];
                                }

                                if (blnInitial) {
                                    this.storeAppState();
                                }
                            }

                            this.isReady = true;

                            return response;
                        } catch (error) {
                            this.handleRequestError(error, true);
                        }
                    },

                    /**
                     * Apply the filter changes
                     */
                    applyFilterRequest: async function (activeResourceTypeId, activeResourceId, activeWeekStart) {

                        this.deleteMessages();

                        if (this.accessLost) {
                            return;
                        }

                        this.isBusy = true;

                        const formData = new FormData();
                        this.appendAppState(formData, {resType: activeResourceTypeId, res: activeResourceId, date: activeWeekStart});

                        try {
                            const response = await this.apiFetch('GET', this.options.apiUrls.calendar, formData);

                            if (!response.ok) {
                                await this.throwResponseError(response);
                            }

                            this.checkResponse(response);

                            const json = await response.json();

                            if (json.status === 'success') {
                                if (this.activeWeekStart && parseInt(this.activeWeekStart) !== parseInt(json.data['activeWeekStart'])) {
                                    this.isBusy = false;
                                    return;
                                }

                                this.messages = json.messages ? json.messages : [];

                                for (const key of Object.keys(json.data)) {
                                    this[key] = json.data[key];
                                }

                                this.storeAppState();
                            }
                        } catch (error) {
                            this.handleRequestError(error);
                        }
                        this.isBusy = false;
                    },

                    /**
                     * Send booking request
                     */
                    bookingRequest: async function () {
                        this.deleteMessages();
                        this.clearFieldErrors();
                        this.isBusy = true;

                        let form = this.$el.querySelector('.rbb-js-booking-form');

                        if (!form) {
                            console.error('Form not found');
                        }

                        const formData = new FormData(form);
                        formData.append('resourceId', this.bookingWindow.activeTimeSlot.resourceId);
                        this.appendAppState(formData);

                        // Without the field (e.g. a custom template without repetitions): only the selected week
                        if (!formData.get('repeatUntil')) {
                            formData.set('repeatUntil', String(this.activeWeekStart));
                        }

                        for (const selectedTimeSlot of this.bookingWindow.selectedTimeSlots) {
                            formData.append('slots[]', selectedTimeSlot);
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
                                const response = await this.apiFetch('POST', this.options.apiUrls.bookings, formData);

                                if (!response.ok) {
                                    await this.throwResponseError(response);
                                }

                                this.checkResponse(response);

                                const json = await response.json();

                                this.bookingWindow.response = json.data;
                                this.messages = json.messages ? json.messages : [];
                                this.setFieldErrors(json.fieldErrors);

                                if (json.status === 'success') {
                                    // The dialog stays open (no time limit for reading the message):
                                    // the "book" button disappears, so move the focus to the close button.
                                    this.focusCloseButton();

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
                                this.handleRequestError(error);

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
                        this.isBusy = true;

                        const formData = new FormData();
                        formData.append('resourceId', this.bookingWindow.activeTimeSlot.resourceId);
                        // Without the field (e.g. a custom template without repetitions): only the selected week
                        const ctrlRepeatUntil = this.$el.querySelector('[name="repeatUntil"]');
                        formData.append('repeatUntil', ctrlRepeatUntil && ctrlRepeatUntil.value ? ctrlRepeatUntil.value : String(this.activeWeekStart));
                        this.appendAppState(formData);
                        const ctrlRequestedItems = this.$el.querySelector('[name="requestedItems"]');
                        formData.append('requestedItems', ctrlRequestedItems ? ctrlRequestedItems.value : '1');

                        for (const selectedTimeSlot of this.bookingWindow.selectedTimeSlots) {
                            formData.append('slots[]', selectedTimeSlot);
                        }

                        try {
                            const response = await this.apiFetch('POST', this.options.apiUrls.validateBooking, formData);

                            if (!response.ok) {
                                await this.throwResponseError(response);
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
                            this.handleRequestError(error);
                        }
                        this.isBusy = false;
                    },

                    /**
                     * Send cancel booking request
                     */
                    cancelBookingRequest: async function () {
                        this.deleteMessages();

                        this.bookingWindow.showCancelBookingForm = false;
                        this.bookingWindow.showCancelBookingButton = false;

                        // DELETE {bookings}/{id}, the parameters are sent in the query string
                        const params = new FormData();
                        this.appendAppState(params);

                        if (this.bookingWindow.deleteRepetitions) {
                            params.append('deleteRepetitions', '1');
                        }

                        const url = this.options.apiUrls.bookings.replace(/\/$/, '') + '/' + encodeURIComponent(String(this.bookingWindow.booking.id));

                        try {
                            const response = await this.apiFetch('DELETE', url, params);

                            if (!response.ok) {
                                await this.throwResponseError(response);
                            }

                            this.checkResponse(response);

                            const json = await response.json();

                            this.bookingWindow.response = json.data;

                            if (json.status === 'success') {
                                // The dialog stays open, the "cancel" button disappears
                                this.focusCloseButton();
                            }

                            // Always
                            this.messages = json.messages ? json.messages : [];
                            this.bookingWindow.deleteRepetitions = false;
                            await this.refreshDataRequest();

                        } catch (error) {
                            this.handleRequestError(error);

                            // Always
                            this.refreshDataRequest();
                            this.bookingWindow.deleteRepetitions = false;
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
                        if (tstamp === this.activeWeekStart || tstamp < this.filterBoard.firstPermittedWeekStart || tstamp > this.filterBoard.lastPermittedWeekStart) {
                            return false;
                        }

                        // Vue watcher will trigger this.applyFilterRequest()
                        this.activeWeekStart = tstamp;
                    },

                    /**
                     * @param slot
                     * @param action
                     * @param booking
                     */
                    openBookingWindow: async function (slot, action, booking = null) {
                        this.deleteMessages();
                        this.clearFieldErrors();
                        this.mode = 'booking-window';
                        this.isBusy = true;
                        // Small screens: the slot list shows the day of the clicked slot
                        this.bookingWindowDayIndex = slot.index;

                        // Open the booking window as modal dialog: the focus stays in the dialog,
                        // Esc closes it and the browser returns the focus to the button afterwards.
                        await this.$nextTick();
                        const dialog = this.$refs.bookingDialog;

                        if (dialog && !dialog.open && typeof dialog.showModal === 'function') {
                            dialog.showModal();
                        }

                        // Reset
                        await (() => {
                            this.bookingWindow = {
                                'action': action,
                                'activeTimeSlot': slot,
                                'booking': booking,
                                'response': {},
                                'deleteRepetitions': false,
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

                            let weekRepeatOptions = this.$el.querySelectorAll('.rbb-js-booking-form .rbb-js-repeat-until option');

                            if (weekRepeatOptions.length > 0) {
                                for (const elOption of weekRepeatOptions) {
                                    elOption.removeAttribute('selected')
                                }
                            }
                        })();

                        this.isBusy = false;
                    },

                    /**
                     * Small screens: select the previous (step = -1) or next (step = 1) day.
                     * @param step
                     * @param focusTab
                     */
                    selectAdjacentDay: function (step, focusTab = false) {
                        const position = this.weekdays.findIndex(weekday => weekday.index === this.activeDayIndex);
                        const next = this.weekdays[position + step];

                        if (!next) {
                            return;
                        }

                        this.activeDayIndex = next.index;

                        if (focusTab) {
                            this.$nextTick(() => {
                                const tab = this.$el.querySelector(`.rbb-day-tab[data-day-index="${next.index}"]`);

                                if (tab) {
                                    tab.focus();
                                }
                            });
                        }
                    },

                    /**
                     * Links the messages per form field to their fields (only within this module):
                     * aria-invalid, aria-describedby (id of the message in the list above the form)
                     * and the CSS class rbb-is-invalid. The focus moves to the first invalid field.
                     * @param fieldErrors object field name => message
                     */
                    setFieldErrors: function (fieldErrors) {
                        this.clearFieldErrors();

                        if (!fieldErrors || typeof fieldErrors !== 'object' || Array.isArray(fieldErrors) || !Object.keys(fieldErrors).length) {
                            return;
                        }

                        this.fieldErrors = {...fieldErrors};

                        this.$nextTick(() => {
                            let firstInvalidField = null;

                            for (const field of Object.keys(this.fieldErrors)) {
                                for (const input of this.getFormFields(field)) {
                                    const describedBy = input.getAttribute('aria-describedby') || '';
                                    input.dataset.rbbDescribedby = describedBy;
                                    input.setAttribute('aria-describedby', (describedBy + ' ' + this.getFieldErrorId(field)).trim());
                                    input.setAttribute('aria-invalid', 'true');
                                    input.classList.add('rbb-is-invalid');

                                    // The mark disappears as soon as the field is changed
                                    input.addEventListener('input', () => this.clearFieldError(field), {once: true});
                                    input.addEventListener('change', () => this.clearFieldError(field), {once: true});

                                    firstInvalidField = firstInvalidField || input;
                                }
                            }

                            if (firstInvalidField) {
                                firstInvalidField.focus();
                            }
                        });
                    },

                    clearFieldError: function (field) {
                        if (!(field in this.fieldErrors)) {
                            return;
                        }

                        for (const input of this.getFormFields(field)) {
                            this.resetFormField(input);
                        }

                        const fieldErrors = {...this.fieldErrors};
                        delete fieldErrors[field];
                        this.fieldErrors = fieldErrors;
                    },

                    clearFieldErrors: function () {
                        for (const input of this.$el.querySelectorAll('.rbb-is-invalid')) {
                            this.resetFormField(input);
                        }

                        this.fieldErrors = {};
                    },

                    resetFormField: function (input) {
                        const describedBy = input.dataset.rbbDescribedby || '';
                        describedBy ? input.setAttribute('aria-describedby', describedBy) : input.removeAttribute('aria-describedby');
                        delete input.dataset.rbbDescribedby;
                        input.removeAttribute('aria-invalid');
                        input.classList.remove('rbb-is-invalid');
                    },

                    /**
                     * Form fields with the given name in the booking form of this module
                     * (never in other modules on the same page).
                     * @param field
                     */
                    getFormFields: function (field) {
                        const form = this.$el.querySelector('.rbb-booking-window .rbb-js-booking-form');

                        if (!form) {
                            return [];
                        }

                        const name = CSS.escape(field);

                        return Array.from(form.querySelectorAll(`[name="${name}"], [name="${name}[]"]`));
                    },

                    /**
                     * The server does not store anything between the requests: every request
                     * carries the selection (resource type, resource, week). Module and page
                     * are part of the access token.
                     */
                    appendAppState: function (formData, state = null) {
                        state = state || {
                            resType: this.activeResourceTypeId,
                            res: this.activeResourceId,
                            date: this.activeWeekStart,
                        };

                        for (const key of ['resType', 'res', 'date']) {
                            // A direct link may contain the date as "2026-11-20"
                            if (key === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(String(state[key]))) {
                                formData.append(key, String(state[key]));
                                continue;
                            }

                            const value = parseInt(state[key], 10);
                            formData.append(key, value > 0 ? String(value) : '');
                        }
                    },

                    /**
                     * The selection is kept per browser tab (sessionStorage), per page (incl. query string)
                     * and per module, so several week calendars on one page do not interfere.
                     */
                    getAppStateStorageKey: function () {
                        return 'rbb:' + window.location.pathname + window.location.search + ':' + this.options.moduleKey;
                    },

                    storeAppState: function () {
                        try {
                            window.sessionStorage.setItem(this.getAppStateStorageKey(), JSON.stringify({
                                resType: this.activeResourceTypeId,
                                res: this.activeResourceId,
                                date: this.activeWeekStart,
                            }));
                        } catch (e) {
                            // Storage not available (e.g. private mode): the selection is not kept on reload
                        }
                    },

                    /**
                     * Selection on page load: the last selection in this tab, otherwise the
                     * query parameters of a direct link (?resType=…&res=…&date=…).
                     */
                    getInitialAppState: function () {
                        try {
                            const stored = JSON.parse(window.sessionStorage.getItem(this.getAppStateStorageKey()) || 'null');

                            if (stored && typeof stored === 'object') {
                                return stored;
                            }
                        } catch (e) {
                            // Storage not available or invalid content
                        }

                        const params = new URLSearchParams(window.location.search);

                        return {
                            resType: params.get('resType'),
                            res: params.get('res'),
                            date: params.get('date'),
                        };
                    },

                    /**
                     * Id of the message of a field, unique on the page (contains the module key).
                     * @param field
                     */
                    getFieldErrorId: function (field) {
                        return 'rbbFieldError_' + this.options.moduleKey + '_' + String(field).replace(/[^A-Za-z0-9_-]/g, '_');
                    },

                    /**
                     * Moves the focus to the close button of the booking window, e.g. after a
                     * successful booking (the button that had the focus is no longer displayed).
                     */
                    focusCloseButton: function () {
                        this.$nextTick(() => {
                            const button = this.$el.querySelector('.rbb-booking-window .rbb-js-close-booking-window');

                            if (button) {
                                button.focus();
                            }
                        });
                    },

                    /**
                     * Number of selected time slots of a day in the booking window.
                     * @param dayIndex
                     */
                    countSelectedSlotsOfDay: function (dayIndex) {
                        let count = 0;

                        for (const row of this.rows) {
                            for (const slot of row.cellData) {
                                if (slot.index === dayIndex && this.bookingWindow.selectedTimeSlots.includes(slot.bookingCheckboxValue)) {
                                    count++;
                                }
                            }
                        }

                        return count;
                    },

                    onTableTouchStart: function (evt) {
                        const touch = evt.changedTouches[0];
                        this.touchStart = {x: touch.clientX, y: touch.clientY};
                    },

                    /**
                     * Small screens: swipe left/right to show the next/previous day.
                     */
                    onTableTouchEnd: function (evt) {
                        if (!this.touchStart) {
                            return;
                        }

                        const touch = evt.changedTouches[0];
                        const dx = touch.clientX - this.touchStart.x;
                        const dy = touch.clientY - this.touchStart.y;
                        this.touchStart = null;

                        // Horizontal swipe of at least 50px
                        if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy) * 1.5) {
                            return;
                        }

                        this.selectAdjacentDay(dx < 0 ? 1 : -1);
                    },

                    getShortWeekday: function (weekday) {
                        if (weekday.tstamp) {
                            return new Date(weekday.tstamp * 1000).toLocaleDateString(document.documentElement.lang || undefined, {weekday: 'short'});
                        }

                        return String(weekday.title).substring(0, 2);
                    },

                    getShortDate: function (weekday) {
                        if (weekday.tstamp) {
                            return new Date(weekday.tstamp * 1000).toLocaleDateString(document.documentElement.lang || undefined, {day: 'numeric', month: 'numeric'});
                        }

                        return weekday.date;
                    },

                    /**
                     * Hide booking window
                     */
                    hideBookingWindow: function () {
                        this.deleteMessages();
                        this.clearFieldErrors();
                        this.mode = 'week-calendar';

                        const dialog = this.$refs.bookingDialog;

                        if (dialog && dialog.open) {
                            dialog.close();
                        }
                    },

                    /**
                     * Request to the API of the week calendar (WeekCalendarApiController).
                     * GET and DELETE send the data in the query string, POST in the body.
                     * Every request carries the access token, write requests also the CSRF token.
                     * @param method
                     * @param url
                     * @param data FormData
                     */
                    apiFetch: async function (method, url, data = null) {
                        const init = {
                            method: method,
                            credentials: 'same-origin',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-Rbb-Access-Token': this.options.accessToken,
                            },
                        };

                        if (method !== 'GET') {
                            init.headers['X-Csrf-Token'] = this.options.requestToken;
                        }

                        if (data && method === 'POST') {
                            init.body = data;
                        } else if (data) {
                            const query = new URLSearchParams(data).toString();

                            if (query) {
                                url += (url.includes('?') ? '&' : '?') + query;
                            }
                        }

                        const response = await fetch(url, init);

                        this.lastResponseCode = response.status;
                        // The server answered: the connection is back
                        this.connectionLost = false;

                        // Token expired, logged in or out in another tab, page no longer accessible
                        if (response.status === 401 || response.status === 403) {
                            this.onAccessLost(response.status);
                        }

                        return response;
                    },

                    /**
                     * The API does not accept the access token any more: stop the periodic
                     * requests and ask the visitor to reload the page (a new token is created).
                     * @param status
                     */
                    onAccessLost: function (status) {
                        if (this.accessLost) {
                            return;
                        }

                        this.accessLost = true;

                        for (const key of Object.keys(this.intervals)) {
                            window.clearInterval(this.intervals[key]);
                        }

                        this.setMessage('error', status === 401 ? this.options.texts.accessTokenExpired : this.options.texts.accessDenied);
                    },

                    /**
                     * Check json response
                     * @param response
                     */
                    checkResponse: function (response) {
                        this.lastResponseCode = response.status;
                        this.isReady = response.status === 200;
                        // The server answered: the connection is back
                        this.connectionLost = false;
                    },

                    /**
                     * Throw an error for a failed HTTP request. The server answers errors of the
                     * week calendar with JSON (status "error"), its message is displayed.
                     * @param response
                     */
                    throwResponseError: async function (response) {
                        let message = null;

                        try {
                            const json = await response.json();
                            message = json && json.messages ? json.messages.error : null;
                        } catch (e) {
                            // No JSON (e.g. an error page of the web server)
                        }

                        const error = new Error(message || (response.status + ', ' + response.statusText));
                        error.serverMessage = message;

                        throw error;
                    },

                    /**
                     * Network errors (the server could not be reached): the displayed data is kept,
                     * a note is displayed and the data is fetched again automatically. For other errors
                     * (e.g. HTTP 400 or 500) the message of the server is displayed, if there is one.
                     * @param error
                     * @param isRefresh true for the periodic refresh of the week calendar
                     */
                    handleRequestError: function (error, isRefresh = false) {
                        console.error('Fetch', error);

                        // The message to reload the page is already displayed (onAccessLost)
                        if (this.accessLost) {
                            this.isReady = false;

                            return;
                        }

                        if (error instanceof TypeError) {
                            this.connectionLost = true;

                            if (!isRefresh) {
                                this.setMessage('error', this.options.texts.networkError);
                            }

                            return;
                        }

                        // Error message of the server (JSON response), not repeated on every refresh
                        if (error.serverMessage && !isRefresh) {
                            this.setMessage('error', error.serverMessage);
                        }

                        this.isReady = false;
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
                            console.error(`Invalid message type "${type}" detected!`);
                            return;
                        }

                        this.messages[type] = message;
                    },

                    deleteMessage: function (type) {
                        const accepted = ['error', 'confirm', 'warning', 'info'];

                        if (-1 === accepted.indexOf(type)) {
                            console.error(`Invalid message type "${type}" detected!`);
                            return;
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
            app.config.globalProperties.window = window;
            app.mount(elId);
        }
    }
}
