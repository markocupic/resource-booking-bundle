/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

/**
 * Helpers for the HTML rendered on the server (week view and booking window).
 */

const TEXT_FIELDS = 'input[name]:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), textarea[name]';

/**
 * Replaces the content of the container. The focused element keeps the focus if it still
 * exists (same id). keepTypedValues: text typed in the meantime is kept (fields with the same name).
 *
 * @param {HTMLElement} container
 * @param {string} html
 * @param {{keepTypedValues?: boolean}} options
 * @returns {boolean} false if the HTML did not change
 */
export function replaceHtml(container, html, {keepTypedValues = false} = {}) {
    if (container.innerHTML === html) {
        return false;
    }

    const active = container.ownerDocument.activeElement;
    const focusId = active && active.id && container.contains(active) ? active.id : null;
    const typed = {};

    if (keepTypedValues) {
        for (const field of container.querySelectorAll(TEXT_FIELDS)) {
            typed[field.name] = field.value;
        }
    }

    container.innerHTML = html;

    if (keepTypedValues) {
        for (const field of container.querySelectorAll(TEXT_FIELDS)) {
            if (field.name in typed) {
                field.value = typed[field.name];
            }
        }
    }

    if (focusId) {
        const element = container.ownerDocument.getElementById(focusId);

        if (element && container.contains(element)) {
            element.focus();
        }
    }

    return true;
}

/**
 * The selection of the week view: the selects of the filter (rendered on the server).
 *
 * @param {HTMLElement} view
 * @returns {{resType: string|null, res: string|null, date: string|null, dateString: string|null}}
 */
export function readSelection(view) {
    const value = (name) => {
        const field = view.querySelector(`select[name="${name}"]`);

        return field && field.value && field.value !== 'undefined' ? field.value : null;
    };

    const dateField = view.querySelector('select[name="date"]');
    const option = dateField ? dateField.selectedOptions[0] : null;

    return {
        resType: value('resType'),
        res: value('res'),
        date: value('date'),
        dateString: option && option.dataset.rbbDate ? option.dataset.rbbDate : null,
    };
}

/**
 * Small screens: the day displayed in the table and the pressed day tab.
 *
 * @param {HTMLElement} view
 * @param {number|null} dayIndex
 */
export function applyDayState(view, dayIndex) {
    const table = view.querySelector('.rbb-booking-table-main');

    if (table) {
        dayIndex === null ? table.removeAttribute('data-activedayindex') : table.setAttribute('data-activedayindex', String(dayIndex));
    }

    for (const tab of view.querySelectorAll('[data-rbb-day]')) {
        tab.setAttribute('aria-pressed', parseInt(tab.dataset.rbbDay, 10) === dayIndex ? 'true' : 'false');
    }
}

/**
 * Indexes of the days of the week view (day tabs).
 *
 * @param {HTMLElement} view
 * @returns {number[]}
 */
export function getDayIndexes(view) {
    return Array.from(view.querySelectorAll('[data-rbb-day]')).map((tab) => parseInt(tab.dataset.rbbDay, 10));
}

/**
 * Day displayed on small screens: the current day if it is still displayed, otherwise
 * today or the first day of the week.
 *
 * @param {HTMLElement} view
 * @param {number|null} current
 * @param {Date} now
 * @returns {number|null}
 */
export function resolveDayIndex(view, current, now = new Date()) {
    const tabs = Array.from(view.querySelectorAll('[data-rbb-day]'));

    if (!tabs.length) {
        return null;
    }

    if (tabs.some((tab) => parseInt(tab.dataset.rbbDay, 10) === current)) {
        return current;
    }

    const today = now.toDateString();
    const tabToday = tabs.find((tab) => tab.dataset.rbbTstamp && new Date(parseInt(tab.dataset.rbbTstamp, 10) * 1000).toDateString() === today);

    return parseInt((tabToday || tabs[0]).dataset.rbbDay, 10);
}

/**
 * Small screens: the slot list of the booking window shows the slots of one day.
 *
 * @param {HTMLElement} view
 * @param {number|null} dayIndex
 */
export function applyBookingDayState(view, dayIndex) {
    for (const chip of view.querySelectorAll('[data-rbb-booking-day]')) {
        chip.setAttribute('aria-pressed', parseInt(chip.dataset.rbbBookingDay, 10) === dayIndex ? 'true' : 'false');
    }

    for (const item of view.querySelectorAll('.rbb-booking-slot-list-item[data-day-index]')) {
        item.hidden = parseInt(item.dataset.dayIndex, 10) !== dayIndex;
    }
}

/**
 * The checkboxes of the time slots exist twice (table and list on small screens):
 * the other checkboxes with the same value get the same state.
 *
 * @param {HTMLElement} container
 * @param {HTMLInputElement} checkbox
 */
export function syncSlotCheckboxes(container, checkbox) {
    for (const other of container.querySelectorAll('[data-rbb-slot]')) {
        if (other !== checkbox && other.type === 'checkbox' && other.value === checkbox.value) {
            other.checked = checkbox.checked;
        }
    }
}

/**
 * Writes the messages into the container, with the markup of the templates
 * <template data-rbb-message-template="<type>"> (text in [data-rbb-message-text]).
 *
 * @param {HTMLElement} container
 * @param {Object<string, string|null>} messages
 * @param {HTMLElement} templates Element containing the templates
 * @param {string[]} types Displayed types, in this order
 */
export function renderMessages(container, messages, templates, types = ['error', 'warning', 'confirm', 'info']) {
    container.replaceChildren();

    for (const type of types) {
        const message = messages[type];

        if (!message) {
            continue;
        }

        const template = templates.querySelector(`template[data-rbb-message-template="${type}"]`);
        let element;

        if (template) {
            element = template.content.firstElementChild.cloneNode(true);
            const text = element.querySelector('[data-rbb-message-text]') || element;
            text.textContent = message;
        } else {
            element = container.ownerDocument.createElement('div');
            element.className = 'rbb-alert rbb-alert--' + (type === 'error' ? 'danger' : type === 'confirm' ? 'success' : type);
            element.dataset.rbbMessage = type;
            element.textContent = message;
        }

        container.appendChild(element);
    }
}

/**
 * Id of the message of a field, unique on the page (same as RenderBookingWindowListener).
 *
 * @param {string} moduleKey
 * @param {string} field
 * @returns {string}
 */
export function getFieldErrorId(moduleKey, field) {
    return 'rbbFieldError_' + moduleKey + '_' + String(field).replace(/[^A-Za-z0-9_-]/g, '_');
}

/**
 * Marks the fields with a message (rendered on the server in the list above the form):
 * aria-invalid, aria-describedby and the CSS class rbb-is-invalid. The mark disappears as
 * soon as the field is changed. Returns the first invalid field.
 *
 * @param {HTMLFormElement|null} form
 * @param {Object<string, string>} fieldErrors
 * @param {string} moduleKey
 * @returns {HTMLElement|null}
 */
export function markFieldErrors(form, fieldErrors, moduleKey) {
    if (!form || !fieldErrors || typeof fieldErrors !== 'object' || Array.isArray(fieldErrors)) {
        return null;
    }

    let firstInvalidField = null;

    for (const field of Object.keys(fieldErrors)) {
        const inputs = Array.from(form.querySelectorAll('[name]')).filter((input) => input.name === field || input.name === field + '[]');

        for (const input of inputs) {
            const describedBy = input.getAttribute('aria-describedby') || '';
            input.dataset.rbbDescribedby = describedBy;
            input.setAttribute('aria-describedby', (describedBy + ' ' + getFieldErrorId(moduleKey, field)).trim());
            input.setAttribute('aria-invalid', 'true');
            input.classList.add('rbb-is-invalid');

            const reset = () => resetField(input);
            input.addEventListener('input', reset, {once: true});
            input.addEventListener('change', reset, {once: true});

            firstInvalidField = firstInvalidField || input;
        }
    }

    return firstInvalidField;
}

/**
 * @param {HTMLElement} input
 */
export function resetField(input) {
    if (!input.classList.contains('rbb-is-invalid')) {
        return;
    }

    const describedBy = input.dataset.rbbDescribedby || '';
    describedBy ? input.setAttribute('aria-describedby', describedBy) : input.removeAttribute('aria-describedby');
    delete input.dataset.rbbDescribedby;
    input.removeAttribute('aria-invalid');
    input.classList.remove('rbb-is-invalid');
}

/**
 * URL of the page with the selection (link to the displayed week), other parameters are kept.
 *
 * @param {string} href
 * @param {{resType: *, res: *, dateString: *}} selection
 * @returns {string}
 */
export function buildSelectionUrl(href, selection) {
    const url = new URL(href);

    for (const [key, value] of [['resType', selection.resType], ['res', selection.res], ['date', selection.dateString]]) {
        value ? url.searchParams.set(key, String(value)) : url.searchParams.delete(key);
    }

    return url.toString();
}
