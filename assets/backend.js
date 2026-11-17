/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

/*
 * Back end widget "rbbBlockedSlots" (blocked periods in the mode "slots"):
 * week view of a resource, a click on a time slot blocks it or removes the block.
 * The selection is kept in the hidden input "<timeSlot>:<Y-m-d>,…",
 * so that the slots of other weeks are kept while browsing.
 */
(function () {
    'use strict';

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function format(text, ...values) {
        let i = 0;

        return String(text).replace(/%s/g, () => (i < values.length ? values[i++] : ''));
    }

    function init(element) {
        if (element.dataset.rbbInitialized) {
            return;
        }

        element.dataset.rbbInitialized = '1';

        const input = document.getElementById(element.dataset.input);
        const calendar = element.querySelector('.rbb-blocked-slots__calendar');
        const labels = JSON.parse(element.dataset.labels || '{}');

        // Selected slots and the number of bookings and the label of the slots: the server
        // provides the selected slots with bookings, the loaded weeks add the other slots
        const selected = new Set(input.value.split(',').filter((value) => '' !== value));
        const slotInfo = new Map(Object.entries(JSON.parse(element.dataset.slotsWithBookings || '{}')));

        // Data of the list of all blocked slots (modal): labels of the time slots, slots of the other blocked periods
        const listData = JSON.parse(element.dataset.list || '{}');
        const timeSlotLabels = listData.timeSlots || {};
        const otherSlots = listData.otherSlots || [];
        const dateFormat = new Intl.DateTimeFormat(listData.locale || undefined, {weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric'});
        const now = new Date();
        const today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');

        let week = parseInt(element.dataset.week, 10);
        let data = null;

        // Modal with the list of all blocked slots, outside the calendar (not replaced by render())
        const dialog = document.createElement('dialog');
        dialog.className = 'rbb-blocked-slots__dialog';
        dialog.setAttribute('aria-labelledby', element.id + '_dialog_title');
        element.appendChild(dialog);

        function save() {
            // Sorted by date and time slot, like BlockedSlots::normalize()
            const sortKey = (value) => value.split(':')[1] + ':' + value.split(':')[0].padStart(10, '0');

            input.value = Array.from(selected).sort((a, b) => sortKey(a).localeCompare(sortKey(b))).join(',');
        }

        // Selected slots with bookings, sorted by date: [value, {bookingCount, label}]
        function getSelectedWithBookings() {
            return Array.from(selected)
                .filter((value) => (slotInfo.get(value)?.bookingCount || 0) > 0)
                .sort((a, b) => a.split(':')[1].localeCompare(b.split(':')[1]))
                .map((value) => [value, slotInfo.get(value)]);
        }

        // Timestamp (seconds) within the week of a slot value "<timeSlot>:<Y-m-d>"
        function getWeekOfValue(value) {
            return Math.floor(new Date(value.split(':')[1] + 'T12:00:00').getTime() / 1000);
        }

        // E.g. "Mi., 18.11.2026"
        function formatDate(date) {
            return dateFormat.format(new Date(date + 'T12:00:00'));
        }

        // E.g. "10:00 – 12:00"
        function getTimeLabel(timeSlot) {
            return timeSlotLabels[timeSlot] || format(labels.unknownTimeSlot, timeSlot);
        }

        // Contao like listing (tl_listing): own slots with the delete icon, the slots of the
        // other blocked periods are grayed out and read only
        function renderList() {
            const own = Array.from(selected).sort((a, b) => {
                const [tsA, dateA] = a.split(':');
                const [tsB, dateB] = b.split(':');

                return dateA === dateB ? parseInt(tsA, 10) - parseInt(tsB, 10) : dateA.localeCompare(dateB);
            });

            let html = '<div class="rbb-blocked-slots__dialog-header"><h3 id="' + element.id + '_dialog_title">' + escapeHtml(labels.listTitle) + '</h3>'
                + '<button type="button" class="rbb-blocked-slots__close" data-action="close" title="' + escapeHtml(labels.close) + '" aria-label="' + escapeHtml(labels.close) + '">'
                + '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" fill="none"/></svg>'
                + '</button></div>'
                + '<div class="rbb-blocked-slots__dialog-body">'
                + '<h4>' + escapeHtml(labels.ownSlots) + ' (' + own.length + ')</h4>';

            if (0 === own.length) {
                html += '<p class="tl_info">' + escapeHtml(labels.noOwnSlots) + '</p>';
            } else {
                html += '<div class="tl_listing_container"><table class="tl_listing showColumns rbb-blocked-slots__list"><thead><tr>'
                    + '<th class="tl_folder_tlist">' + escapeHtml(labels.colDate) + '</th>'
                    + '<th class="tl_folder_tlist">' + escapeHtml(labels.colTime) + '</th>'
                    + '<th class="tl_folder_tlist">' + escapeHtml(labels.colBookings) + '</th>'
                    + '<th class="tl_folder_tlist tl_right_nowrap"></th>'
                    + '</tr></thead><tbody>';

                own.forEach((value, i) => {
                    const [timeSlot, date] = value.split(':');
                    const bookingCount = slotInfo.get(value)?.bookingCount || 0;

                    html += '<tr class="' + (i % 2 ? 'odd' : 'even') + ' hover-row' + (date < today ? ' rbb-blocked-slots__row--past' : '') + '">'
                        + '<td class="tl_file_list"><button type="button" class="rbb-blocked-slots__goto" data-week="' + getWeekOfValue(value) + '" title="' + escapeHtml(labels.showWeek) + '">'
                        + escapeHtml(formatDate(date)) + '</button></td>'
                        + '<td class="tl_file_list">' + escapeHtml(getTimeLabel(timeSlot)) + '</td>'
                        + '<td class="tl_file_list' + (bookingCount > 0 ? ' rbb-blocked-slots__list-bookings' : '') + '">' + (bookingCount > 0 ? bookingCount : '–') + '</td>'
                        + '<td class="tl_file_list tl_right_nowrap">'
                        + '<button type="button" class="rbb-blocked-slots__remove" data-remove="' + escapeHtml(value) + '" title="' + escapeHtml(labels.remove) + '">'
                        + '<img src="' + escapeHtml(listData.deleteIcon || '') + '" width="16" height="16" alt="' + escapeHtml(labels.remove) + '"></button>'
                        + '</td></tr>';
                });

                html += '</tbody></table></div>';
            }

            if (otherSlots.length > 0) {
                html += '<h4>' + escapeHtml(labels.otherSlots) + ' (' + otherSlots.length + ')</h4>'
                    + '<div class="tl_listing_container"><table class="tl_listing showColumns rbb-blocked-slots__list rbb-blocked-slots__list--other"><thead><tr>'
                    + '<th class="tl_folder_tlist">' + escapeHtml(labels.colDate) + '</th>'
                    + '<th class="tl_folder_tlist">' + escapeHtml(labels.colTime) + '</th>'
                    + '<th class="tl_folder_tlist">' + escapeHtml(labels.colPeriod) + '</th>'
                    + '</tr></thead><tbody>';

                otherSlots.forEach((slot, i) => {
                    html += '<tr class="' + (i % 2 ? 'odd' : 'even') + '">'
                        + '<td class="tl_file_list">' + escapeHtml(formatDate(slot.date)) + '</td>'
                        + '<td class="tl_file_list">' + escapeHtml(getTimeLabel(slot.timeSlot)) + '</td>'
                        + '<td class="tl_file_list">' + escapeHtml(slot.title) + (slot.published ? '' : ' (' + escapeHtml(labels.unpublished) + ')') + '</td>'
                        + '</tr>';
                });

                html += '</tbody></table></div>';
            }

            html += '<p class="tl_help tl_tip">' + escapeHtml(labels.removedHint) + '</p></div>';

            dialog.innerHTML = html;
        }

        dialog.addEventListener('click', (event) => {
            // Click on the backdrop
            if (event.target === dialog) {
                dialog.close();

                return;
            }

            if (event.target.closest('[data-action="close"]')) {
                dialog.close();

                return;
            }

            const goto = event.target.closest('button[data-week]');

            if (goto) {
                dialog.close();
                load(parseInt(goto.dataset.week, 10));

                return;
            }

            const remove = event.target.closest('[data-remove]');

            if (remove) {
                selected.delete(remove.dataset.remove);
                save();
                render();
                renderList();
            }
        });

        function renderCell(cell) {
            if (!cell.available) {
                return '<td class="rbb-blocked-slots__cell rbb-blocked-slots__cell--unavailable"></td>';
            }

            const isSelected = selected.has(cell.value);
            const classes = ['rbb-blocked-slots__cell'];
            const info = [];

            if (isSelected) {
                classes.push('rbb-blocked-slots__cell--selected');
                info.push(labels.selected);
            }

            if (cell.bookingCount > 0) {
                classes.push('rbb-blocked-slots__cell--booked');
                info.push(format(labels.bookings, cell.bookingCount));
            }

            if (cell.blockedBy.length > 0) {
                classes.push('rbb-blocked-slots__cell--blocked-by-other');
                info.push(format(labels.blockedBy, cell.blockedBy.join(', ')));
            }

            if (cell.isPast) {
                classes.push('rbb-blocked-slots__cell--past');
            }

            // Past slots and slots blocked by another blocked period cannot be selected (unless already selected)
            const disabled = !isSelected && (cell.isPast || cell.blockedBy.length > 0);
            const text = info.length ? info.map(escapeHtml).join('<br>') : '&nbsp;';

            return '<td class="' + classes.join(' ') + '">'
                + '<button type="button" class="rbb-blocked-slots__toggle" data-value="' + escapeHtml(cell.value) + '"'
                + ' aria-pressed="' + (isSelected ? 'true' : 'false') + '"'
                + (disabled ? ' disabled title="' + escapeHtml(cell.isPast ? labels.past : info.join(', ')) + '"' : '')
                + '>' + text + '</button></td>';
        }

        function render() {
            if (!data) {
                return;
            }

            // Discreet: the dates are in the table header, the date range is the tooltip
            const weekLabel = '<span class="rbb-blocked-slots__week" title="' + escapeHtml(data.week.label) + '">' + escapeHtml(format(labels.calendarWeek, data.week.number, data.week.year)) + '</span>';

            let html = '<div class="rbb-blocked-slots__toolbar">'
                + '<button type="button" class="tl_submit" data-week="' + data.week.prev + '">&larr; ' + escapeHtml(labels.prevWeek) + '</button>'
                + '<button type="button" class="tl_submit" data-week="0">' + escapeHtml(labels.today) + '</button>'
                + '<button type="button" class="tl_submit" data-week="' + data.week.next + '">' + escapeHtml(labels.nextWeek) + ' &rarr;</button>'
                // Without table (no time slots) the calendar week is shown here, otherwise in the table header
                + (0 === data.rows.length ? weekLabel : '')
                + '<button type="button" class="tl_submit rbb-blocked-slots__show-list" data-action="list">' + escapeHtml(format(labels.showList, selected.size)) + '</button>'
                + '<span class="rbb-blocked-slots__count">' + escapeHtml(format(labels.selectedCount, selected.size)) + '</span>'
                + '</div>';

            if (0 === data.rows.length) {
                html += '<p class="tl_info">' + escapeHtml(labels.noTimeSlots) + '</p>';
            } else {
                html += '<div class="rbb-blocked-slots__scroll"><table class="tl_listing rbb-blocked-slots__table"><thead><tr><th class="rbb-blocked-slots__time">' + weekLabel + '</th>';

                data.days.forEach((day) => {
                    html += '<th><span class="rbb-blocked-slots__weekday">' + escapeHtml(day.weekdayLabel || day.label) + '</span>'
                        + (day.dateLabel ? '<br><span class="rbb-blocked-slots__date">' + escapeHtml(day.dateLabel) + '</span>' : '') + '</th>';
                });

                html += '</tr></thead><tbody>';

                data.rows.forEach((row) => {
                    html += '<tr><th scope="row" class="rbb-blocked-slots__time">' + escapeHtml(row.label) + '</th>' + row.cells.map(renderCell).join('') + '</tr>';
                });

                html += '</tbody></table></div>';
            }

            // Below the week calendar
            const withBookings = getSelectedWithBookings();

            if (withBookings.length > 0) {
                html += '<div class="tl_error rbb-blocked-slots__warning"><p>' + escapeHtml(format(labels.warningBookings, withBookings.length)) + '</p><ul>';

                withBookings.forEach(([value, info]) => {
                    html += '<li><button type="button" class="rbb-blocked-slots__goto" data-week="' + getWeekOfValue(value) + '" title="' + escapeHtml(labels.showWeek) + '">'
                        + escapeHtml(info.label || value) + '</button> (' + escapeHtml(format(labels.bookings, info.bookingCount)) + ')</li>';
                });

                html += '</ul></div>';
            }

            calendar.innerHTML = html;
        }

        function load(tstamp) {
            const url = new URL(element.dataset.url, window.location.origin);

            url.searchParams.set('resource', element.dataset.resource);
            url.searchParams.set('period', element.dataset.period);

            if (tstamp > 0) {
                url.searchParams.set('week', String(tstamp));
            }

            calendar.classList.add('rbb-blocked-slots__calendar--loading');

            if (!data) {
                calendar.innerHTML = '<p>' + escapeHtml(labels.loading) + '</p>';
            }

            fetch(url.toString(), {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(response.status + ' ' + response.statusText);
                    }

                    return response.json();
                })
                .then((json) => {
                    data = json;
                    week = json.week.start;

                    json.rows.forEach((row) => row.cells.forEach((cell) => {
                        if (cell.available) {
                            slotInfo.set(cell.value, {bookingCount: cell.bookingCount, label: cell.label});
                        }
                    }));

                    render();
                })
                .catch((error) => {
                    console.error(error);
                    calendar.innerHTML = '<p class="tl_error">' + escapeHtml(labels.loadError) + '</p>';
                })
                .finally(() => calendar.classList.remove('rbb-blocked-slots__calendar--loading'));
        }

        calendar.addEventListener('click', (event) => {
            if (event.target.closest('[data-action="list"]')) {
                renderList();
                dialog.showModal();

                return;
            }

            const weekButton = event.target.closest('button[data-week]');

            if (weekButton) {
                load(parseInt(weekButton.dataset.week, 10));

                return;
            }

            const toggle = event.target.closest('.rbb-blocked-slots__toggle');

            if (!toggle || toggle.disabled) {
                return;
            }

            const value = toggle.dataset.value;

            if (selected.has(value)) {
                selected.delete(value);
            } else {
                selected.add(value);
            }

            save();
            render();
        });

        load(week);
    }

    function initAll() {
        document.querySelectorAll('.rbb-blocked-slots').forEach(init);
    }

    window.RbbBlockedSlots = {init, initAll};

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Contao back end with Turbo
    document.addEventListener('turbo:load', initAll);
    document.addEventListener('turbo:render', initAll);
})();
