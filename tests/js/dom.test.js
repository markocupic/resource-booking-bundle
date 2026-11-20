import {beforeEach, describe, expect, it} from 'vitest';
import {
    applyBookingDayState,
    applyDayState,
    buildSelectionUrl,
    getFieldErrorId,
    markFieldErrors,
    readSelection,
    renderMessages,
    replaceHtml,
    resetField,
    resolveDayIndex,
    syncSlotCheckboxes,
} from '../../assets/rbb/dom.js';

const dayTabs = (tstamps) => `
    <div class="rbb-booking-table-main">
        ${tstamps.map((tstamp, index) => `<button data-rbb-day="${index}" data-rbb-tstamp="${tstamp}"></button>`).join('')}
    </div>`;

describe('replaceHtml', () => {
    let container;

    beforeEach(() => {
        document.body.innerHTML = '<div id="c"></div>';
        container = document.getElementById('c');
    });

    it('returns false if nothing changed', () => {
        container.innerHTML = '<p>a</p>';

        expect(replaceHtml(container, '<p>a</p>')).toBe(false);
        expect(replaceHtml(container, '<p>b</p>')).toBe(true);
        expect(container.innerHTML).toBe('<p>b</p>');
    });

    it('keeps the focus and the typed text', () => {
        container.innerHTML = '<input id="f" name="bookingDescription" value=""><input type="checkbox" name="slots[]" value="1">';
        const field = document.getElementById('f');
        field.value = 'typed';
        field.focus();

        replaceHtml(container, '<input id="f" name="bookingDescription" value="server"><input type="checkbox" name="slots[]" value="1" checked>', {keepTypedValues: true});

        expect(document.getElementById('f').value).toBe('typed');
        expect(document.activeElement.id).toBe('f');
        expect(container.querySelector('[type="checkbox"]').checked).toBe(true);
    });

    it('takes the values of the server by default', () => {
        container.innerHTML = '<input id="f" name="x" value="">';
        document.getElementById('f').value = 'typed';

        replaceHtml(container, '<input id="f" name="x" value="server">');

        expect(document.getElementById('f').value).toBe('server');
    });
});

describe('readSelection', () => {
    it('reads the selects of the filter', () => {
        document.body.innerHTML = `
            <div id="v">
                <select name="resType"><option value="2" selected>A</option></select>
                <select name="res"><option value="undefined" selected>-</option></select>
                <select name="date"><option value="1700000000" data-rbb-date="2023-11-13" selected>W</option></select>
            </div>`;

        expect(readSelection(document.getElementById('v'))).toEqual({resType: '2', res: null, date: '1700000000', dateString: '2023-11-13'});
    });

    it('returns null without filter', () => {
        document.body.innerHTML = '<div id="v"></div>';

        expect(readSelection(document.getElementById('v'))).toEqual({resType: null, res: null, date: null, dateString: null});
    });
});

describe('day state', () => {
    it('marks the active day', () => {
        document.body.innerHTML = `<div id="v">${dayTabs([1, 2, 3])}</div>`;
        const view = document.getElementById('v');

        applyDayState(view, 1);

        expect(view.querySelector('.rbb-booking-table-main').dataset.activedayindex).toBe('1');
        expect(Array.from(view.querySelectorAll('[data-rbb-day]')).map((tab) => tab.getAttribute('aria-pressed'))).toEqual(['false', 'true', 'false']);
    });

    it('keeps the current day, otherwise today or the first day', () => {
        const now = new Date(2026, 10, 24, 10);
        const day = (offset) => Math.floor(new Date(2026, 10, 23 + offset).getTime() / 1000);
        document.body.innerHTML = `<div id="v">${dayTabs([day(0), day(1), day(2)])}</div>`;
        const view = document.getElementById('v');

        expect(resolveDayIndex(view, 2, now)).toBe(2);
        expect(resolveDayIndex(view, 9, now)).toBe(1);
        expect(resolveDayIndex(view, null, new Date(2027, 0, 1))).toBe(0);

        document.body.innerHTML = '<div id="v"></div>';
        expect(resolveDayIndex(document.getElementById('v'), 1, now)).toBeNull();
    });

    it('shows the slots of the day in the booking window', () => {
        document.body.innerHTML = `
            <div id="v">
                <button data-rbb-booking-day="0"></button><button data-rbb-booking-day="1"></button>
                <ul>
                    <li class="rbb-booking-slot-list-item" data-day-index="0"></li>
                    <li class="rbb-booking-slot-list-item" data-day-index="1" hidden></li>
                </ul>
            </div>`;
        const view = document.getElementById('v');

        applyBookingDayState(view, 1);

        expect(Array.from(view.querySelectorAll('li')).map((li) => li.hidden)).toEqual([true, false]);
        expect(view.querySelector('[data-rbb-booking-day="1"]').getAttribute('aria-pressed')).toBe('true');
    });
});

describe('syncSlotCheckboxes', () => {
    it('gives the checkboxes with the same value the same state', () => {
        document.body.innerHTML = `
            <div id="v">
                <input type="checkbox" data-rbb-slot value="a"><input type="checkbox" data-rbb-slot value="a"><input type="checkbox" data-rbb-slot value="b">
            </div>`;
        const [first, second, other] = document.querySelectorAll('input');
        first.checked = true;

        syncSlotCheckboxes(document.getElementById('v'), first);

        expect(second.checked).toBe(true);
        expect(other.checked).toBe(false);
    });
});

describe('renderMessages', () => {
    it('renders the messages with the templates, as text', () => {
        document.body.innerHTML = `
            <div id="m"></div>
            <div id="t">
                <template data-rbb-message-template="error"><div class="rbb-alert rbb-alert--danger" data-rbb-message="error"><span data-rbb-message-text></span></div></template>
            </div>`;
        const container = document.getElementById('m');

        renderMessages(container, {error: '<b>Oops</b>', confirm: 'Done', info: null}, document.getElementById('t'));

        const alerts = container.querySelectorAll('[data-rbb-message]');
        expect(alerts).toHaveLength(2);
        expect(alerts[0].className).toBe('rbb-alert rbb-alert--danger');
        expect(alerts[0].textContent).toBe('<b>Oops</b>');
        expect(alerts[0].querySelector('b')).toBeNull();
        // No template: default markup
        expect(alerts[1].className).toBe('rbb-alert rbb-alert--success');

        renderMessages(container, {}, document.getElementById('t'));
        expect(container.children).toHaveLength(0);
    });
});

describe('field errors', () => {
    it('marks the fields and resets them', () => {
        document.body.innerHTML = '<form id="f"><input name="bookingDescription" aria-describedby="hint"><input type="checkbox" name="slots[]"></form>';
        const form = document.getElementById('f');

        const first = markFieldErrors(form, {bookingDescription: 'Required', slots: 'Select'}, 'mod1');

        const input = form.querySelector('[name="bookingDescription"]');
        expect(first).toBe(input);
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(input.getAttribute('aria-describedby')).toBe('hint rbbFieldError_mod1_bookingDescription');
        expect(form.querySelector('[name="slots[]"]').classList.contains('rbb-is-invalid')).toBe(true);

        input.dispatchEvent(new Event('input'));

        expect(input.hasAttribute('aria-invalid')).toBe(false);
        expect(input.getAttribute('aria-describedby')).toBe('hint');

        resetField(input);
        expect(input.getAttribute('aria-describedby')).toBe('hint');
    });

    it('ignores missing errors', () => {
        expect(markFieldErrors(null, {a: 'b'}, 'm')).toBeNull();
        expect(markFieldErrors(document.createElement('form'), [], 'm')).toBeNull();
    });

    it('creates the same ids as the server', () => {
        expect(getFieldErrorId('5_1', 'my field[]')).toBe('rbbFieldError_5_1_my_field__');
    });
});

describe('buildSelectionUrl', () => {
    it('sets the selection and keeps other parameters', () => {
        const url = buildSelectionUrl('https://example.org/buchen.html?foo=1&res=9', {resType: '2', res: null, dateString: '2026-11-23'});

        expect(url).toBe('https://example.org/buchen.html?foo=1&resType=2&date=2026-11-23');
    });
});
