/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

/**
 * Error of a request to the API. serverMessage: the error message of the server
 * (JSON response with status "error"), if there is one.
 */
export class ResponseError extends Error {
    constructor(message, status, serverMessage = null) {
        super(message);
        this.name = 'ResponseError';
        this.status = status;
        this.serverMessage = serverMessage;
    }
}

/**
 * Requests to the API of the week calendar (WeekCalendarApiController). Every request carries
 * the access token, write requests also the CSRF token. GET and DELETE send the data in the
 * query string, POST in the body.
 */
export class ApiClient {
    /**
     * @param {{accessToken: string, requestToken: string}} options
     * @param {Function} fetchFn
     */
    constructor(options, fetchFn = null) {
        this.accessToken = options.accessToken || '';
        this.requestToken = options.requestToken || '';
        this.fetchFn = fetchFn || ((...args) => window.fetch(...args));
    }

    /**
     * @param {string} method GET, POST or DELETE
     * @param {string} url
     * @param {FormData|null} data
     * @returns {Promise<Response>}
     */
    request(method, url, data = null) {
        const init = {
            method: method,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Rbb-Access-Token': this.accessToken,
            },
        };

        if (method !== 'GET') {
            init.headers['X-Csrf-Token'] = this.requestToken;
        }

        if (data && method === 'POST') {
            init.body = data;
        } else if (data) {
            const query = new URLSearchParams(data).toString();

            if (query) {
                url += (url.includes('?') ? '&' : '?') + query;
            }
        }

        return this.fetchFn(url, init);
    }
}

/**
 * The JSON of a response. Failed requests throw a ResponseError with the message of the server.
 * The answers of the booking window are rendered even for some errors: acceptedStatus.
 *
 * @param {Response} response
 * @param {number[]} acceptedStatus HTTP status codes accepted in addition to 2xx
 * @returns {Promise<object>}
 */
export async function readJson(response, acceptedStatus = []) {
    if (!response.ok && !acceptedStatus.includes(response.status)) {
        let message = null;

        try {
            const json = await response.json();
            message = json && json.messages ? json.messages.error : null;
        } catch (e) {
            // No JSON (e.g. an error page of the web server)
        }

        throw new ResponseError(message || (response.status + ', ' + response.statusText), response.status, message);
    }

    return response.json();
}

/**
 * The server does not store anything between the requests: every request carries the
 * selection (resource type, resource, week). Module and page are part of the access token.
 * The date may be a timestamp or a date like "2026-11-23" (link to the page).
 *
 * @param {FormData} formData
 * @param {{resType: *, res: *, date: *}} selection
 */
export function appendSelection(formData, selection) {
    for (const key of ['resType', 'res', 'date']) {
        const value = selection[key];

        if (key === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(String(value))) {
            formData.set(key, String(value));
            continue;
        }

        const number = parseInt(value, 10);
        formData.set(key, number > 0 ? String(number) : '');
    }
}
