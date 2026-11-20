import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {ResourceBookingApp} from '../../assets/rbb/ResourceBookingApp.js';

const weekHtml = (week = '100') => `
    <form class="rbb-filter-form">
        <select name="resType"><option value="1" selected>Rooms</option></select>
        <select name="res"><option value="5" selected>Room 5</option></select>
        <select name="date"><option value="${week}" data-rbb-date="2026-11-23" selected>Week</option></select>
        <button type="button" data-rbb-week="200">Next</button>
    </form>
    <div class="rbb-booking-table-main">
        <button type="button" data-rbb-day="0" data-rbb-tstamp="1">Mo</button>
        <button type="button" data-rbb-day="1" data-rbb-tstamp="2">Tu</button>
        <div data-day-index="1">
            <button type="button" data-rbb-action="book" data-rbb-slot="slot-1" data-rbb-resource="5">Book</button>
            <button type="button" data-rbb-action="cancel" data-rbb-booking-id="12" data-rbb-booking-uuid="ab-12">Cancel</button>
        </div>
    </div>`;

const bookingWindowHtml = `
    <form class="rbb-js-booking-form">
        <input type="checkbox" name="slots[]" value="slot-1" data-rbb-slot checked>
        <input name="bookingDescription" value="">
        <select name="requestedItems"><option value="1" selected>1</option></select>
    </form>
    <button type="button" data-rbb-action="submit-booking">Book</button>
    <button type="button" class="rbb-js-close-booking-window" data-rbb-action="close">Close</button>`;

const moduleHtml = (initialWeekHtml) => `
    <div id="app" class="rbb-app" data-rbb-app>
        <div data-rbb-loading></div>
        <div data-rbb-connection-lost hidden></div>
        <div data-rbb-status="401" hidden></div>
        <div data-rbb-status="error" hidden></div>
        <div class="rbb-week-calendar" data-rbb-week-calendar aria-busy="false">
            <div data-rbb-messages="week"></div>
            <div data-rbb-week-calendar-view>${initialWeekHtml}</div>
        </div>
        <dialog data-rbb-dialog>
            <div class="rbb-booking-window" aria-busy="false">
                <div data-rbb-messages="window"></div>
                <div data-rbb-booking-window-view></div>
            </div>
        </dialog>
        <template data-rbb-message-template="error"><div class="rbb-alert rbb-alert--danger" data-rbb-message="error"><span data-rbb-message-text></span></div></template>
    </div>`;

const json = (body, status = 200) => new Response(JSON.stringify(body), {status: status, headers: {'Content-Type': 'application/json'}});

const options = {
    requestToken: 'csrf',
    moduleKey: '7_1',
    accessToken: 'token',
    apiUrls: {calendar: '/api/calendar', validateBooking: '/api/bookings/validate', bookings: '/api/bookings', action: '/api/actions/__action__'},
    texts: {networkError: 'Network error', accessTokenExpired: 'Reload', accessDenied: 'Denied'},
};

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

function createApp(fetch, initialWeekHtml = weekHtml()) {
    document.body.innerHTML = moduleHtml(initialWeekHtml);

    return new ResourceBookingApp(document.getElementById('app'), options, {fetch: fetch, refreshInterval: 0});
}

describe('ResourceBookingApp', () => {
    beforeEach(() => {
        // jsdom has no modal dialogs
        HTMLDialogElement.prototype.showModal = function () {
            this.setAttribute('open', '');
        };
        HTMLDialogElement.prototype.close = function () {
            this.removeAttribute('open');
        };
        vi.spyOn(console, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('uses the week calendar rendered with the page', () => {
        const fetch = vi.fn();
        const app = createApp(fetch);

        expect(fetch).not.toHaveBeenCalled();
        expect(app.root.classList.contains('rbb-is-ready')).toBe(true);
        expect(document.querySelector('[data-rbb-loading]').hidden).toBe(true);
        // Day of the week view on small screens: today is not displayed, so the first day
        expect(document.querySelector('[data-rbb-day="0"]').getAttribute('aria-pressed')).toBe('true');
    });

    it('keeps the messages rendered with the page', () => {
        document.body.innerHTML = moduleHtml(weekHtml()).replace('<div data-rbb-messages="week"></div>', '<div data-rbb-messages="week"><div class="rbb-alert" data-rbb-message="info"> No resource </div></div>');
        const app = new ResourceBookingApp(document.getElementById('app'), options, {fetch: vi.fn(), refreshInterval: 0});

        expect(app.getMessage('info')).toBe('No resource');
        expect(document.querySelector('[data-rbb-messages="week"]').textContent).toBe('No resource');
    });

    it('loads the week calendar if it was not rendered with the page', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {weekCalendarHtml: weekHtml()}, messages: {}}));
        const app = createApp(fetch, '');
        await flush();

        const [url] = fetch.mock.calls[0];
        expect(url).toContain('/api/calendar?initial=1');
        expect(app.weekView.querySelector('select[name="res"]')).not.toBeNull();
        expect(app.root.classList.contains('rbb-is-ready')).toBe(true);
    });

    it('shows the error status if the week calendar cannot be loaded', async () => {
        const fetch = vi.fn(async () => json({status: 'error', messages: {error: 'Broken'}}, 500));
        createApp(fetch, '');
        await flush();
        await flush();

        expect(document.querySelector('[data-rbb-status="error"]').hidden).toBe(false);
        expect(document.querySelector('[data-rbb-messages="week"]').textContent).toContain('Broken');
    });

    it('loads another week and displays only the last response', async () => {
        const resolvers = [];
        const fetch = vi.fn(() => new Promise((resolve) => resolvers.push(resolve)));
        const app = createApp(fetch);

        document.querySelector('[data-rbb-week="200"]').click();
        app.loadCalendar({resType: 1, res: 5, date: 300});

        expect(fetch.mock.calls[0][0]).toContain('date=200');
        expect(app.weekCalendar.getAttribute('aria-busy')).toBe('true');

        resolvers[1](json({status: 'success', data: {weekCalendarHtml: weekHtml('300')}, messages: {}}));
        await flush();
        resolvers[0](json({status: 'success', data: {weekCalendarHtml: weekHtml('200')}, messages: {}}));
        await flush();

        expect(app.weekView.querySelector('select[name="date"]').value).toBe('300');
        expect(app.weekCalendar.getAttribute('aria-busy')).toBe('false');
    });

    it('selects the day with the day tabs and the arrow keys', () => {
        const app = createApp(vi.fn());

        document.querySelector('[data-rbb-day="1"]').click();
        expect(app.activeDayIndex).toBe(1);

        document.querySelector('[data-rbb-day="1"]').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowLeft', bubbles: true}));
        expect(app.activeDayIndex).toBe(0);
        expect(document.activeElement).toBe(document.querySelector('[data-rbb-day="0"]'));
    });

    it('opens the booking window when the content is there', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {bookingWindowHtml: bookingWindowHtml}, messages: {}}));
        const app = createApp(fetch);

        const opening = app.openBookingWindow('showBookingForm', {slot: 'slot-1', resourceId: '5', dayIndex: 1});
        expect(app.dialog.open).toBe(false);
        await opening;

        const [url, init] = fetch.mock.calls[0];
        expect(url).toBe('/api/bookings/validate');
        expect(init.body.getAll('slots[]')).toEqual(['slot-1']);
        expect(init.body.get('resourceId')).toBe('5');
        expect(init.body.get('date')).toBe('100');
        expect(init.body.get('repeatUntil')).toBe('100');
        expect(app.dialog.open).toBe(true);
        expect(app.mode).toBe('booking-window');
        expect(document.activeElement.name).toBe('slots[]');
    });

    it('opens the booking window with a click on a time slot', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {bookingWindowHtml: bookingWindowHtml}, messages: {}}));
        const app = createApp(fetch);
        const spy = vi.spyOn(app, 'openBookingWindow');

        document.querySelector('[data-rbb-action="book"]').click();
        document.querySelector('[data-rbb-action="cancel"]').click();

        expect(spy).toHaveBeenNthCalledWith(1, 'showBookingForm', {slot: 'slot-1', resourceId: '5', dayIndex: 1});
        expect(spy).toHaveBeenNthCalledWith(2, 'showCancelBookingForm', {dayIndex: 1, booking: {id: 12, bookingUuid: 'ab-12'}});
    });

    it('does not open the dialog without content', async () => {
        const fetch = vi.fn(async () => {
            throw new TypeError('Failed to fetch');
        });
        const app = createApp(fetch);

        await app.openBookingWindow('showBookingForm', {slot: 'slot-1', resourceId: '5'});

        expect(app.dialog.open).toBe(false);
        expect(app.mode).toBe('week-calendar');
        expect(app.connectionLost).toBe(true);
        expect(document.querySelector('[data-rbb-connection-lost]').hidden).toBe(false);
        expect(document.querySelector('[data-rbb-messages="week"]').textContent).toBe('Network error');
    });

    it('opens the cancel window', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {bookingWindowHtml: '<button data-rbb-action="cancel-booking">Cancel</button>'}, messages: {}}));
        const app = createApp(fetch);

        await app.openBookingWindow('showCancelBookingForm', {booking: {id: 12, bookingUuid: 'ab-12'}});

        const [url, init] = fetch.mock.calls[0];
        expect(url).toBe('/api/actions/cancelBookingFormRequest');
        expect(init.body.get('bookingId')).toBe('12');
        expect(app.dialog.open).toBe(true);
    });

    it('sends the booking and keeps the dialog open', async () => {
        const fetch = vi.fn(async (url) => {
            if (url.startsWith('/api/calendar')) {
                return json({status: 'success', data: {weekCalendarHtml: weekHtml()}, messages: {}});
            }

            return json({status: 'success', data: {bookingWindowHtml: bookingWindowHtml}, messages: {}});
        });
        const app = createApp(fetch);
        const after = vi.fn();
        document.addEventListener('rbb_after_booking_request', after, {once: true});

        await app.openBookingWindow('showBookingForm', {slot: 'slot-1', resourceId: '5'});
        document.querySelector('[name="bookingDescription"]').value = 'Meeting';
        await app.bookingRequest();

        const booking = fetch.mock.calls.find(([url]) => url === '/api/bookings');
        expect(booking[1].body.get('bookingDescription')).toBe('Meeting');
        expect(booking[1].headers['X-Csrf-Token']).toBe('csrf');
        expect(after).toHaveBeenCalledOnce();
        expect(after.mock.calls[0][0].detail.instance).toBe(app);
        expect(app.dialog.open).toBe(true);
        expect(fetch.mock.calls.at(-1)[0]).toContain('/api/calendar');
    });

    it('does not send the booking if a listener prevents it', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {bookingWindowHtml: bookingWindowHtml}, messages: {}}));
        const app = createApp(fetch);
        await app.openBookingWindow('showBookingForm', {slot: 'slot-1', resourceId: '5'});

        document.addEventListener('rbb_before_booking_request', (evt) => {
            evt.detail.blnSend = false;
            evt.detail.instance.setMessage('error', 'Not allowed');
        }, {once: true});
        await app.bookingRequest();

        expect(fetch.mock.calls.some(([url]) => url === '/api/bookings')).toBe(false);
        expect(document.querySelector('[data-rbb-messages="window"]').textContent).toBe('Not allowed');
        expect(document.querySelector('[data-rbb-messages="week"]').textContent).toBe('');
    });

    it('displays the messages of the browser in the HTML of the window', async () => {
        const html = '<div data-rbb-messages="window"></div><div class="rbb-message-box">Server</div>' + bookingWindowHtml;
        const fetch = vi.fn(async () => json({status: 'success', data: {bookingWindowHtml: html}, messages: {}}));
        const app = createApp(fetch);
        await app.openBookingWindow('showBookingForm', {slot: 'slot-1', resourceId: '5'});

        app.setMessage('error', 'First name missing');

        const inner = app.bookingWindowView.querySelector('[data-rbb-messages="window"]');
        expect(inner.textContent).toBe('First name missing');
        expect(inner.querySelector('.rbb-alert--danger')).not.toBeNull();
        expect(app.windowMessages.children).toHaveLength(0);

        // Kept when the window is rendered again
        await app.readBookingWindowResponse(json({status: 'success', data: {bookingWindowHtml: html}, messages: {}}));
        expect(app.bookingWindowView.querySelector('[data-rbb-messages="window"]').textContent).toBe('First name missing');
    });

    it('cancels a booking with DELETE', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {bookingWindowHtml: '<input type="checkbox" name="deleteRepetitions" checked><button class="rbb-js-close-booking-window" data-rbb-action="close">Close</button>', weekCalendarHtml: weekHtml()}, messages: {}}));
        const app = createApp(fetch);
        await app.openBookingWindow('showCancelBookingForm', {booking: {id: 12, bookingUuid: 'ab-12'}});

        await app.cancelBookingRequest();

        const call = fetch.mock.calls.find(([, init]) => init.method === 'DELETE');
        expect(call[0]).toMatch(/^\/api\/bookings\/12\?/);
        expect(call[0]).toContain('deleteRepetitions=1');
        expect(call[0]).toContain('bookingUuid=ab-12');
        expect(document.activeElement.classList.contains('rbb-js-close-booking-window')).toBe(true);
    });

    it('closes the booking window with the close button and Esc', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {bookingWindowHtml: bookingWindowHtml}, messages: {}}));
        const app = createApp(fetch);

        await app.openBookingWindow('showBookingForm', {slot: 'slot-1', resourceId: '5'});
        document.querySelector('[data-rbb-action="close"]').click();
        expect(app.dialog.open).toBe(false);
        expect(app.bookingWindowView.innerHTML).toBe('');

        await app.openBookingWindow('showBookingForm', {slot: 'slot-1', resourceId: '5'});
        app.dialog.dispatchEvent(new Event('cancel', {cancelable: true}));
        expect(app.dialog.open).toBe(false);
        expect(app.mode).toBe('week-calendar');
    });

    it('validates the booking form again after a change', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {bookingWindowHtml: bookingWindowHtml}, messages: {}}));
        const app = createApp(fetch);
        await app.openBookingWindow('showBookingForm', {slot: 'slot-1', resourceId: '5'});
        fetch.mockClear();

        const checkbox = app.bookingWindowView.querySelector('[data-rbb-slot]');
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change', {bubbles: true}));
        await flush();

        expect(fetch).toHaveBeenCalledOnce();
        expect(fetch.mock.calls[0][1].body.getAll('slots[]')).toEqual([]);
    });

    it('stops and asks to reload the page if the access token expired', async () => {
        const fetch = vi.fn(async () => json({status: 'error', messages: {error: 'Expired'}}, 401));
        const app = createApp(fetch);

        await app.refreshDataRequest();
        await app.refreshDataRequest();

        expect(fetch).toHaveBeenCalledOnce();
        expect(app.accessLost).toBe(true);
        expect(app.getMessage('error')).toBe('Reload');
    });

    it('validates the message types', () => {
        const app = createApp(vi.fn());

        app.setMessage('info', 'Hello');
        app.setMessage('foo', 'Bar');
        expect(app.getMessage('info')).toBe('Hello');
        expect(app.getMessage('foo')).toBeNull();

        app.deleteMessage('info');
        expect(document.querySelector('[data-rbb-messages="week"]').children).toHaveLength(0);
    });

    it('updates the address of the page after a change of the week', async () => {
        const fetch = vi.fn(async () => json({status: 'success', data: {weekCalendarHtml: weekHtml('200')}, messages: {}}));
        const app = createApp(fetch);

        await app.loadCalendar({resType: 1, res: 5, date: 200});

        expect(window.location.search).toBe('?resType=1&res=5&date=2026-11-23');
        expect(app).toBeInstanceOf(ResourceBookingApp);
    });
});
