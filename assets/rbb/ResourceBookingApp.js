/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

import {ApiClient, appendSelection, readJson} from './api.js';
import {
    applyBookingDayState,
    applyDayState,
    buildSelectionUrl,
    getDayIndexes,
    markFieldErrors,
    readSelection,
    renderMessages,
    replaceHtml,
    resetField,
    resolveDayIndex,
    syncSlotCheckboxes,
} from './dom.js';

const MESSAGE_TYPES = ['error', 'confirm', 'warning', 'info'];

/**
 * Week calendar of the front end module (template frontend_module/resource_booking_weekcalendar).
 *
 * The server renders the week view and the booking window (HTML): this class loads the HTML,
 * inserts it and handles the clicks, changes and keys (data-rbb-* attributes). The selection
 * (resource type, resource, week) is read from the filter of the week view.
 *
 * Public for other scripts (e.g. listeners of the events rbb_before_booking_request and
 * rbb_after_booking_request): options, mode, bookingWindow, setMessage(), getMessage(),
 * deleteMessage(), deleteMessages(), apiFetch(), appendAppState(), refreshDataRequest().
 */
export class ResourceBookingApp {
    /**
     * @param {HTMLElement|string} root The element [data-rbb-app] of the module (or a selector)
     * @param {object} options requestToken, moduleKey, accessToken, apiUrls, texts
     * @param {{fetch?: Function, window?: Window, refreshInterval?: number, idleTimeout?: number}} environment For tests
     */
    constructor(root, options = {}, environment = {}) {
        this.root = typeof root === 'string' ? document.querySelector(root) : root;
        this.window = environment.window || window;
        this.document = this.root.ownerDocument;

        this.options = {
            requestToken: '',
            moduleKey: '',
            accessToken: '',
            // URLs of the API (WeekCalendarApiController); "__action__" in "action" is replaced by the action
            apiUrls: {calendar: '', validateBooking: '', bookings: '', action: ''},
            // Texts for errors detected in the browser (translated in the module template)
            texts: {
                networkError: 'The request could not be sent. Please check your internet connection and try again.',
                accessTokenExpired: 'Please reload the page.',
                accessDenied: 'You do not have access to this calendar. Please reload the page.',
            },
            ...options,
        };
        this.options.texts = {...this.options.texts, ...(options.texts || {})};
        this.options.apiUrls = {...{calendar: '', validateBooking: '', bookings: '', action: ''}, ...(options.apiUrls || {})};

        this.api = new ApiClient(this.options, environment.fetch || null);

        // Elements of the module template
        this.weekCalendar = this.root.querySelector('[data-rbb-week-calendar]');
        this.weekView = this.root.querySelector('[data-rbb-week-calendar-view]');
        this.weekMessages = this.root.querySelector('[data-rbb-messages="week"]');
        this.dialog = this.root.querySelector('[data-rbb-dialog]');
        this.bookingWindowElement = this.dialog ? this.dialog.querySelector('.rbb-booking-window') : null;
        this.bookingWindowView = this.root.querySelector('[data-rbb-booking-window-view]');
        // Fallback for the messages of the browser if the HTML of the booking window has no place for them
        this.windowMessages = Array.from(this.root.querySelectorAll('[data-rbb-messages="window"]')).find((element) => !this.bookingWindowView || !this.bookingWindowView.contains(element)) || null;

        // week-calendar or booking-window (the booking window is open)
        this.mode = 'week-calendar';
        // Booking window: action (showBookingForm|showCancelBookingForm), slot, resource and booking to cancel
        this.bookingWindow = {action: null, slot: null, resourceId: null, booking: null};
        this.messages = {error: null, confirm: null, warning: null, info: null};
        this.isBusy = false;
        this.isIdle = false;
        // The server could not be reached (network error)
        this.connectionLost = false;
        // The API refused the access token (HTTP 401/403): the page has to be reloaded
        this.accessLost = false;
        // Day displayed on small screens (week view and booking window)
        this.activeDayIndex = null;
        this.bookingWindowDayIndex = null;
        // Only the response of the last request of the week calendar is displayed
        this.calendarRequestId = 0;
        this.intervals = [];
        this.touchStart = null;

        // Messages rendered with the page (e.g. no resource found)
        if (this.weekMessages) {
            for (const element of this.weekMessages.querySelectorAll('[data-rbb-message]')) {
                if (MESSAGE_TYPES.includes(element.dataset.rbbMessage)) {
                    const text = element.querySelector('[data-rbb-message-text]') || element;
                    this.messages[element.dataset.rbbMessage] = text.textContent.trim();
                }
            }
        }

        this.bindEvents();
        this.applyWeekViewState();
        this.renderMessages();

        this.refreshInterval = environment.refreshInterval ?? 15000;
        this.idleTimeout = environment.idleTimeout ?? 300000;

        this.start();
    }

    /**
     * Load the week calendar if it was not rendered with the page, then refresh it periodically.
     */
    start() {
        if (!this.weekView.innerHTML.trim()) {
            this.loadCalendar(this.getSelectionFromUrl(), {initial: true});
        } else {
            this.setReady();
        }

        if (this.refreshInterval > 0) {
            // Fetch data from server, but not while the tab is hidden or the user is idle
            this.intervals.push(this.window.setInterval(() => {
                if (!this.isIdle && this.document.visibilityState !== 'hidden') {
                    this.refreshDataRequest();
                }
            }, this.refreshInterval));

            // Back in the tab or online again: show the current data right away
            this.document.addEventListener('visibilitychange', () => {
                if (this.document.visibilityState === 'visible') {
                    this.refreshDataRequest();
                }
            });

            this.window.addEventListener('online', () => this.refreshDataRequest());

            if (this.idleTimeout > 0) {
                this.initializeIdleDetector(this.document, this.idleTimeout);
            }
        }
    }

    bindEvents() {
        this.weekView.addEventListener('click', (evt) => this.onWeekCalendarClick(evt));
        this.weekView.addEventListener('change', (evt) => this.onWeekCalendarChange(evt));
        this.weekView.addEventListener('keydown', (evt) => this.onWeekCalendarKeydown(evt));
        this.weekView.addEventListener('touchstart', (evt) => this.onTableTouchStart(evt), {passive: true});
        this.weekView.addEventListener('touchend', (evt) => this.onTableTouchEnd(evt));

        // Without JavaScript the filter is a form (GET): with JavaScript it is never sent
        this.weekView.addEventListener('submit', (evt) => evt.preventDefault());

        if (this.bookingWindowView) {
            this.bookingWindowView.addEventListener('click', (evt) => this.onBookingWindowClick(evt));
            this.bookingWindowView.addEventListener('change', (evt) => this.onBookingWindowChange(evt));
            this.bookingWindowView.addEventListener('submit', (evt) => {
                evt.preventDefault();
                this.bookingRequest();
            });
        }

        if (this.dialog) {
            // Esc
            this.dialog.addEventListener('cancel', (evt) => {
                evt.preventDefault();
                this.hideBookingWindow();
            });
        }
    }

    // -------------------------------------------------------------------------
    // Week view
    // -------------------------------------------------------------------------

    /**
     * Selection of the filter (resource type, resource, week).
     */
    getSelection() {
        return readSelection(this.weekView);
    }

    /**
     * Selection of a link to the page (?resType=…&res=…&date=…), used if the week calendar
     * could not be rendered with the page.
     */
    getSelectionFromUrl() {
        const params = new URLSearchParams(this.window.location.search);

        return {resType: params.get('resType'), res: params.get('res'), date: params.get('date')};
    }

    /**
     * Refresh the week calendar (periodically, after a booking or a cancellation).
     */
    async refreshDataRequest() {
        return this.loadCalendar(this.getSelection(), {isRefresh: true});
    }

    /**
     * Loads the week view (HTML rendered on the server) for the selection. Only the response of
     * the last request is displayed (e.g. several weeks selected quickly one after the other).
     *
     * @param {object} selection
     * @param {{initial?: boolean, isRefresh?: boolean}} options
     */
    async loadCalendar(selection, {initial = false, isRefresh = false} = {}) {
        if (this.accessLost) {
            return;
        }

        const requestId = ++this.calendarRequestId;
        const formData = new FormData();

        if (initial) {
            // The server selects a resource automatically if nothing is selected
            formData.append('initial', '1');
        }

        appendSelection(formData, selection);

        if (!isRefresh) {
            this.deleteMessages();
            this.setBusy(true);
        }

        try {
            const json = await readJson(await this.apiFetch('GET', this.options.apiUrls.calendar, formData));

            if (requestId !== this.calendarRequestId) {
                return;
            }

            if (json.status === 'success' && json.data && typeof json.data.weekCalendarHtml === 'string') {
                this.setWeekCalendarHtml(json.data.weekCalendarHtml);

                if (!isRefresh) {
                    this.setMessages(json.messages || {});
                    this.updateUrl(initial);
                }
            }

            this.setReady();
        } catch (error) {
            if (requestId === this.calendarRequestId) {
                this.handleRequestError(error, isRefresh);
            }
        } finally {
            if (!isRefresh && requestId === this.calendarRequestId) {
                this.setBusy(false);
            }
        }
    }

    /**
     * @param {string} html
     */
    setWeekCalendarHtml(html) {
        if (replaceHtml(this.weekView, html)) {
            this.applyWeekViewState();
        }
    }

    /**
     * Small screens: keep the selected day, otherwise show today (or the first day).
     */
    applyWeekViewState() {
        this.activeDayIndex = resolveDayIndex(this.weekView, this.activeDayIndex);
        applyDayState(this.weekView, this.activeDayIndex);
    }

    /**
     * The address of the page shows the selection: reloading the page or sharing the link
     * shows the same week. Only with a single week calendar on the page.
     *
     * @param {boolean} replaceOnly
     */
    updateUrl(replaceOnly = false) {
        if (replaceOnly || this.document.querySelectorAll('[data-rbb-app]').length !== 1 || !this.window.history || !this.window.history.replaceState) {
            return;
        }

        try {
            this.window.history.replaceState(this.window.history.state, '', buildSelectionUrl(this.window.location.href, this.getSelection()));
        } catch (e) {
            // e.g. sandboxed iframe
        }
    }

    /**
     * @param {Event} evt
     */
    onWeekCalendarClick(evt) {
        const target = evt.target instanceof Element ? evt.target : null;

        if (!target) {
            return;
        }

        const weekButton = target.closest('[data-rbb-week]');

        if (weekButton) {
            evt.preventDefault();

            if (!weekButton.disabled && weekButton.getAttribute('aria-disabled') !== 'true') {
                this.loadCalendar({...this.getSelection(), date: weekButton.dataset.rbbWeek});
            }

            return;
        }

        const dayTab = target.closest('[data-rbb-day]');

        if (dayTab) {
            this.activeDayIndex = parseInt(dayTab.dataset.rbbDay, 10);
            applyDayState(this.weekView, this.activeDayIndex);

            return;
        }

        const button = target.closest('[data-rbb-action]');

        if (!button) {
            return;
        }

        const cell = button.closest('[data-day-index]');
        const dayIndex = cell ? parseInt(cell.dataset.dayIndex, 10) : null;

        if (button.dataset.rbbAction === 'book') {
            this.openBookingWindow('showBookingForm', {slot: button.dataset.rbbSlot, resourceId: button.dataset.rbbResource, dayIndex: dayIndex});
        } else if (button.dataset.rbbAction === 'cancel') {
            this.openBookingWindow('showCancelBookingForm', {
                dayIndex: dayIndex,
                booking: {id: parseInt(button.dataset.rbbBookingId, 10), bookingUuid: button.dataset.rbbBookingUuid || ''},
            });
        }
    }

    /**
     * Changes in the filter (resource type, resource, week).
     *
     * @param {Event} evt
     */
    onWeekCalendarChange(evt) {
        const field = evt.target;

        if (field && ['resType', 'res', 'date'].includes(field.name)) {
            this.loadCalendar(this.getSelection());
        }
    }

    /**
     * Day tabs (small screens): arrow keys select the previous/next day.
     *
     * @param {KeyboardEvent} evt
     */
    onWeekCalendarKeydown(evt) {
        if (!(evt.target instanceof Element) || !evt.target.closest('[data-rbb-day]')) {
            return;
        }

        if (evt.key === 'ArrowLeft' || evt.key === 'ArrowRight') {
            evt.preventDefault();
            this.selectAdjacentDay(evt.key === 'ArrowLeft' ? -1 : 1, true);
        }
    }

    /**
     * Small screens: select the previous (step = -1) or next (step = 1) day.
     *
     * @param {number} step
     * @param {boolean} focusTab
     */
    selectAdjacentDay(step, focusTab = false) {
        const days = getDayIndexes(this.weekView);
        const next = days[days.indexOf(this.activeDayIndex) + step];

        if (next === undefined) {
            return;
        }

        this.activeDayIndex = next;
        applyDayState(this.weekView, next);

        if (focusTab) {
            const tab = this.weekView.querySelector(`[data-rbb-day="${next}"]`);

            if (tab) {
                tab.focus();
            }
        }
    }

    /**
     * @param {TouchEvent} evt
     */
    onTableTouchStart(evt) {
        // Swipes only in the table, not in the filter
        if (!(evt.target instanceof Element) || !evt.target.closest('.rbb-booking-table-main')) {
            this.touchStart = null;

            return;
        }

        const touch = evt.changedTouches[0];
        this.touchStart = {x: touch.clientX, y: touch.clientY};
    }

    /**
     * Small screens: swipe left/right to show the next/previous day.
     *
     * @param {TouchEvent} evt
     */
    onTableTouchEnd(evt) {
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
    }

    // -------------------------------------------------------------------------
    // Booking window
    // -------------------------------------------------------------------------

    /**
     * Opens the booking window (showBookingForm) or the cancel window (showCancelBookingForm).
     * The content is loaded first (rendered on the server): the dialog is opened only when
     * the content is there.
     *
     * @param {string} action
     * @param {{slot?: string, resourceId?: string, dayIndex?: number|null, booking?: {id: number, bookingUuid: string}}} target
     */
    async openBookingWindow(action, {slot = null, resourceId = null, dayIndex = null, booking = null} = {}) {
        // A window is already being loaded (e.g. double click)
        if (this.isBusy && this.mode === 'booking-window') {
            return;
        }

        this.deleteMessages();
        this.mode = 'booking-window';
        this.bookingWindow = {action: action, slot: slot, resourceId: resourceId, booking: booking};
        // Small screens: the slot list shows the day of the clicked slot
        this.bookingWindowDayIndex = dayIndex;
        replaceHtml(this.bookingWindowView, '');

        if (action === 'showBookingForm') {
            await this.bookingFormValidationRequest([slot]);
        } else if (action === 'showCancelBookingForm' && booking) {
            await this.cancelBookingFormRequest();
        }

        if (!this.bookingWindowView.innerHTML.trim()) {
            // No content (e.g. network error): the message is displayed above the week calendar
            this.mode = 'week-calendar';
            this.renderMessages();

            return;
        }

        this.dialog.classList.toggle('rbb-booking-dialog--wide', action === 'showBookingForm');

        // Modal dialog: the focus stays in the dialog, Esc closes it and the browser returns
        // the focus to the button afterwards
        if (!this.dialog.open) {
            typeof this.dialog.showModal === 'function' ? this.dialog.showModal() : this.dialog.setAttribute('open', '');
        }

        this.renderMessages();

        // The first field of the window gets the focus
        const field = this.bookingWindowView.querySelector('input:not([type="hidden"]), select, button:not(.rbb-close-window)');

        if (field && !this.bookingWindowView.contains(this.document.activeElement)) {
            field.focus();
        }
    }

    hideBookingWindow() {
        this.deleteMessages();
        this.mode = 'week-calendar';

        if (this.dialog.open) {
            typeof this.dialog.close === 'function' ? this.dialog.close() : this.dialog.removeAttribute('open');
        }

        replaceHtml(this.bookingWindowView, '');
        this.renderMessages();
    }

    /**
     * Fields of the booking form (rendered on the server) and the selection of the week
     * calendar. Without form (window not loaded yet): the given time slots.
     *
     * @param {string[]|null} slots
     * @returns {FormData}
     */
    getBookingFormData(slots = null) {
        const form = this.getBookingForm();
        const formData = form ? new FormData(form) : new FormData();

        for (const slot of slots || []) {
            formData.append('slots[]', slot);
        }

        formData.set('resourceId', String(this.bookingWindow.resourceId || ''));
        this.appendAppState(formData);

        // Without the field (e.g. a custom template without repetitions): only the selected week
        if (!formData.get('repeatUntil')) {
            formData.set('repeatUntil', String(this.getSelection().date || ''));
        }

        if (!formData.get('requestedItems')) {
            formData.set('requestedItems', '1');
        }

        return formData;
    }

    getBookingForm() {
        return this.bookingWindowView.querySelector('.rbb-js-booking-form');
    }

    /**
     * Booking preview: sends the booking form, the server renders the window again
     * (availability of the selected time slots, messages).
     *
     * @param {string[]|null} slots Selected time slots when the window is opened (no form yet)
     */
    async bookingFormValidationRequest(slots = null) {
        this.deleteMessages();
        this.setBusy(true);

        try {
            await this.readBookingWindowResponse(await this.apiFetch('POST', this.options.apiUrls.validateBooking, this.getBookingFormData(slots)));
        } catch (error) {
            this.handleRequestError(error);
        }

        this.setBusy(false);
    }

    /**
     * Opens the cancel window: the server checks the booking and renders the window.
     */
    async cancelBookingFormRequest() {
        this.deleteMessages();
        this.setBusy(true);

        const formData = new FormData();
        formData.append('bookingId', String(this.bookingWindow.booking.id));
        this.appendAppState(formData);

        try {
            await this.readBookingWindowResponse(await this.apiFetch('POST', this.options.apiUrls.action.replace('__action__', 'cancelBookingFormRequest'), formData));
        } catch (error) {
            this.handleRequestError(error);
        }

        this.setBusy(false);
    }

    /**
     * Send booking request. Other scripts can check or change the form data before
     * (event rbb_before_booking_request) and react afterwards (rbb_after_booking_request).
     */
    async bookingRequest() {
        if (this.isBusy) {
            return;
        }

        this.deleteMessages();
        this.setBusy(true);

        const formData = this.getBookingFormData();

        const event = new CustomEvent('rbb_before_booking_request', {
            detail: {
                instance: this,
                formData: formData,
                blnSend: true,
            },
        });

        this.document.dispatchEvent(event);

        if (event.detail.blnSend === true) {
            try {
                const response = await this.apiFetch('POST', this.options.apiUrls.bookings, formData);
                const json = await this.readBookingWindowResponse(response);
                const firstInvalidField = markFieldErrors(this.getBookingForm(), json.fieldErrors, this.options.moduleKey);

                if (firstInvalidField) {
                    firstInvalidField.focus();
                }

                if (json.status === 'success') {
                    // The dialog stays open (no time limit for reading the message): the "book"
                    // button disappears, so move the focus to the close button
                    this.focusCloseButton();

                    await this.refreshDataRequest();

                    this.document.dispatchEvent(new CustomEvent('rbb_after_booking_request', {
                        detail: {
                            instance: this,
                            formData: formData,
                            response: response,
                        },
                    }));
                }
            } catch (error) {
                this.handleRequestError(error);

                // Always
                await this.refreshDataRequest();
            }
        }

        this.setBusy(false);
    }

    /**
     * Send cancel booking request.
     */
    async cancelBookingRequest() {
        if (this.isBusy) {
            return;
        }

        this.deleteMessages();
        this.setBusy(true);

        // DELETE {bookings}/{id}, the parameters are sent in the query string
        const params = new FormData();
        this.appendAppState(params);

        const ctrlDeleteRepetitions = this.bookingWindowView.querySelector('[name="deleteRepetitions"]');

        if (ctrlDeleteRepetitions && ctrlDeleteRepetitions.checked) {
            params.append('deleteRepetitions', '1');
        }

        // Only displayed in the window after the cancellation
        params.append('bookingUuid', String(this.bookingWindow.booking.bookingUuid || ''));

        const url = this.options.apiUrls.bookings.replace(/\/$/, '') + '/' + encodeURIComponent(String(this.bookingWindow.booking.id));

        try {
            const json = await this.readBookingWindowResponse(await this.apiFetch('DELETE', url, params));

            if (json.status === 'success') {
                // The dialog stays open, the "cancel" button disappears
                this.focusCloseButton();
            }
        } catch (error) {
            this.handleRequestError(error);
        }

        this.setBusy(false);

        // Always
        await this.refreshDataRequest();
    }

    /**
     * Response of the booking window: the HTML of the window replaces the current one.
     * The messages of the server are part of the HTML.
     *
     * @param {Response} response
     * @returns {Promise<object>}
     */
    async readBookingWindowResponse(response) {
        const json = await readJson(response, [400]);

        if (json.data && typeof json.data.bookingWindowHtml === 'string') {
            replaceHtml(this.bookingWindowView, json.data.bookingWindowHtml, {keepTypedValues: true});

            // Small screens: the day of the clicked slot, otherwise the first day
            const days = Array.from(this.bookingWindowView.querySelectorAll('[data-rbb-booking-day]')).map((chip) => parseInt(chip.dataset.rbbBookingDay, 10));

            if (days.length && !days.includes(this.bookingWindowDayIndex)) {
                this.bookingWindowDayIndex = days[0];
            }

            applyBookingDayState(this.bookingWindowView, this.bookingWindowDayIndex);

            // The messages of the browser are displayed in the new HTML of the window
            this.renderMessages();
        } else if (json.messages) {
            // No window (e.g. error before the window could be rendered)
            this.setMessages(json.messages);
        }

        return json;
    }

    /**
     * Clicks in the booking window: close, book, cancel and the day buttons.
     *
     * @param {Event} evt
     */
    onBookingWindowClick(evt) {
        const target = evt.target instanceof Element ? evt.target : null;

        if (!target) {
            return;
        }

        const dayChip = target.closest('[data-rbb-booking-day]');

        if (dayChip) {
            this.bookingWindowDayIndex = parseInt(dayChip.dataset.rbbBookingDay, 10);
            applyBookingDayState(this.bookingWindowView, this.bookingWindowDayIndex);

            return;
        }

        const button = target.closest('[data-rbb-action]');

        if (!button) {
            return;
        }

        switch (button.dataset.rbbAction) {
            case 'close':
                this.hideBookingWindow();
                break;
            case 'submit-booking':
                this.bookingRequest();
                break;
            case 'cancel-booking':
                this.cancelBookingRequest();
                break;
        }
    }

    /**
     * Changes in the booking form: the selection of the time slots, the number of items and the
     * repetitions are checked on the server (preview).
     *
     * @param {Event} evt
     */
    onBookingWindowChange(evt) {
        const field = evt.target;

        if (!(field instanceof Element)) {
            return;
        }

        resetField(field);

        if (field.hasAttribute('data-rbb-slot')) {
            syncSlotCheckboxes(this.bookingWindowView, field);
            this.bookingFormValidationRequest();

            return;
        }

        if (field.name === 'requestedItems' || field.name === 'repeatUntil') {
            this.bookingFormValidationRequest();
        }
    }

    /**
     * Moves the focus to the close button of the booking window, e.g. after a successful
     * booking (the button that had the focus is no longer displayed).
     */
    focusCloseButton() {
        const button = this.bookingWindowView.querySelector('.rbb-js-close-booking-window');

        if (button) {
            button.focus();
        }
    }

    // -------------------------------------------------------------------------
    // Requests, errors and state
    // -------------------------------------------------------------------------

    /**
     * Request to the API of the week calendar. Kept for other scripts (custom actions):
     * apiFetch('POST', app.options.apiUrls.action.replace('__action__', 'myAction'), formData).
     *
     * @param {string} method
     * @param {string} url
     * @param {FormData|null} data
     * @returns {Promise<Response>}
     */
    async apiFetch(method, url, data = null) {
        const response = await this.api.request(method, url, data);

        // The server answered: the connection is back
        this.setConnectionLost(false);

        // Token expired, logged in or out in another tab, page no longer accessible
        if (response.status === 401 || response.status === 403) {
            this.onAccessLost(response.status);
        }

        return response;
    }

    /**
     * Adds the selection of the week calendar (resource type, resource, week) to the data.
     *
     * @param {FormData} formData
     */
    appendAppState(formData) {
        appendSelection(formData, this.getSelection());
    }

    /**
     * The API does not accept the access token any more: stop the periodic requests and
     * ask the visitor to reload the page (a new token is created).
     *
     * @param {number} status
     */
    onAccessLost(status) {
        if (this.accessLost) {
            return;
        }

        this.accessLost = true;
        this.stop();
        this.setMessage('error', status === 401 ? this.options.texts.accessTokenExpired : this.options.texts.accessDenied);
    }

    /**
     * Network errors (the server could not be reached): the displayed data is kept, a note is
     * displayed and the data is fetched again automatically. For other errors (e.g. HTTP 400
     * or 500) the message of the server is displayed, if there is one.
     *
     * @param {Error} error
     * @param {boolean} isRefresh true for the periodic refresh of the week calendar
     */
    handleRequestError(error, isRefresh = false) {
        console.error('Fetch', error);

        if (!this.weekView.innerHTML.trim()) {
            // The week calendar could not be loaded at all
            this.showStatus(error && error.status === 401 ? '401' : 'error');
        }

        // The message to reload the page is already displayed (onAccessLost)
        if (this.accessLost) {
            return;
        }

        if (error instanceof TypeError) {
            this.setConnectionLost(true);

            if (!isRefresh) {
                this.setMessage('error', this.options.texts.networkError);
            }

            return;
        }

        // Error message of the server (JSON response), not repeated on every refresh
        if (error && error.serverMessage && !isRefresh) {
            this.setMessage('error', error.serverMessage);
        }
    }

    /**
     * Stops the periodic requests.
     */
    stop() {
        for (const interval of this.intervals) {
            this.window.clearInterval(interval);
        }

        this.intervals = [];
    }

    /**
     * @param {boolean} isBusy
     */
    setBusy(isBusy) {
        this.isBusy = isBusy;

        for (const element of [this.weekCalendar, this.bookingWindowElement]) {
            if (element) {
                element.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            }
        }
    }

    setReady() {
        this.root.classList.add('rbb-is-ready');

        const loading = this.root.querySelector('[data-rbb-loading]');

        if (loading) {
            loading.hidden = true;
        }

        for (const status of this.root.querySelectorAll('[data-rbb-status]')) {
            status.hidden = true;
        }
    }

    /**
     * @param {string} status 401 or error
     */
    showStatus(status) {
        const loading = this.root.querySelector('[data-rbb-loading]');

        if (loading) {
            loading.hidden = true;
        }

        for (const element of this.root.querySelectorAll('[data-rbb-status]')) {
            element.hidden = element.dataset.rbbStatus !== status;
        }
    }

    /**
     * @param {boolean} connectionLost
     */
    setConnectionLost(connectionLost) {
        this.connectionLost = connectionLost;

        const element = this.root.querySelector('[data-rbb-connection-lost]');

        if (element) {
            element.hidden = !connectionLost;
        }
    }

    /**
     * Idle after the timeout without input: no periodic requests. Back to work: refresh.
     *
     * @param {Document} el
     * @param {number} idleTimeout
     */
    initializeIdleDetector(el, idleTimeout) {
        let lastActivity = Date.now();

        for (const type of ['keydown', 'mousemove', 'mousedown', 'touchstart']) {
            el.addEventListener(type, () => {
                lastActivity = Date.now();

                if (this.isIdle) {
                    this.isIdle = false;
                    this.refreshDataRequest();
                }
            }, {passive: true});
        }

        this.intervals.push(this.window.setInterval(() => {
            if (!this.isIdle && Date.now() - lastActivity >= idleTimeout) {
                this.isIdle = true;
            }
        }, 1000));
    }

    // -------------------------------------------------------------------------
    // Messages of the browser (the messages of the booking window are rendered on the server)
    // -------------------------------------------------------------------------

    /**
     * @param {string} type error, confirm, warning or info
     * @returns {string|null}
     */
    getMessage(type) {
        return this.messages[type] || null;
    }

    /**
     * @param {string} type error, confirm, warning or info
     * @param {string} message
     */
    setMessage(type, message) {
        if (!MESSAGE_TYPES.includes(type)) {
            console.error(`Invalid message type "${type}" detected!`);

            return;
        }

        this.messages[type] = message;
        this.renderMessages();
    }

    /**
     * @param {Object<string, string|null>} messages
     */
    setMessages(messages) {
        for (const type of MESSAGE_TYPES) {
            this.messages[type] = messages[type] || null;
        }

        this.renderMessages();
    }

    /**
     * @param {string} type
     */
    deleteMessage(type) {
        if (!MESSAGE_TYPES.includes(type)) {
            console.error(`Invalid message type "${type}" detected!`);

            return;
        }

        this.messages[type] = null;
        this.renderMessages();
    }

    deleteMessages() {
        this.setMessages({});
    }

    /**
     * The messages are displayed above the week calendar or, while the booking window is open, in the
     * window next to the messages of the server ([data-rbb-messages="window"] in the HTML of the window,
     * otherwise at the top of the window).
     */
    renderMessages() {
        const inWindow = this.mode === 'booking-window';
        const empty = {};
        const windowMessages = (this.bookingWindowView && this.bookingWindowView.querySelector('[data-rbb-messages="window"]')) || this.windowMessages;

        if (this.weekMessages) {
            renderMessages(this.weekMessages, inWindow ? empty : this.messages, this.root);
        }

        for (const container of new Set([windowMessages, this.windowMessages])) {
            if (container) {
                renderMessages(container, inWindow && container === windowMessages ? this.messages : empty, this.root);
            }
        }
    }
}
