import {describe, expect, it, vi} from 'vitest';
import {ApiClient, ResponseError, appendSelection, readJson} from '../../assets/rbb/api.js';

describe('ApiClient', () => {
    it('sends the access token and the data of GET requests in the query string', async () => {
        const fetch = vi.fn(async () => new Response('{}'));
        const client = new ApiClient({accessToken: 'abc', requestToken: 'csrf'}, fetch);
        const data = new FormData();
        data.append('resType', '3');

        await client.request('GET', '/_rbb/api/calendar?x=1', data);

        const [url, init] = fetch.mock.calls[0];
        expect(url).toBe('/_rbb/api/calendar?x=1&resType=3');
        expect(init.method).toBe('GET');
        expect(init.credentials).toBe('same-origin');
        expect(init.headers['X-Rbb-Access-Token']).toBe('abc');
        expect(init.headers['X-Requested-With']).toBe('XMLHttpRequest');
        expect(init.headers).not.toHaveProperty('X-Csrf-Token');
        expect(init.body).toBeUndefined();
    });

    it('sends POST data in the body with the CSRF token', async () => {
        const fetch = vi.fn(async () => new Response('{}'));
        const client = new ApiClient({accessToken: 'abc', requestToken: 'csrf'}, fetch);
        const data = new FormData();
        data.append('slots[]', '1-2');

        await client.request('POST', '/_rbb/api/bookings', data);

        const [url, init] = fetch.mock.calls[0];
        expect(url).toBe('/_rbb/api/bookings');
        expect(init.headers['X-Csrf-Token']).toBe('csrf');
        expect(init.body).toBe(data);
    });

    it('sends DELETE data in the query string with the CSRF token', async () => {
        const fetch = vi.fn(async () => new Response('{}'));
        const client = new ApiClient({accessToken: 'abc', requestToken: 'csrf'}, fetch);
        const data = new FormData();
        data.append('deleteRepetitions', '1');

        await client.request('DELETE', '/_rbb/api/bookings/12', data);

        const [url, init] = fetch.mock.calls[0];
        expect(url).toBe('/_rbb/api/bookings/12?deleteRepetitions=1');
        expect(init.headers['X-Csrf-Token']).toBe('csrf');
        expect(init.body).toBeUndefined();
    });
});

describe('readJson', () => {
    it('returns the JSON of a successful response', async () => {
        await expect(readJson(new Response('{"status":"success"}'))).resolves.toEqual({status: 'success'});
    });

    it('throws an error with the message of the server', async () => {
        const response = new Response('{"status":"error","messages":{"error":"Nope"}}', {status: 500, statusText: 'Server Error'});

        const error = await readJson(response).catch((e) => e);

        expect(error).toBeInstanceOf(ResponseError);
        expect(error.status).toBe(500);
        expect(error.serverMessage).toBe('Nope');
        expect(error.message).toBe('Nope');
    });

    it('throws an error without JSON', async () => {
        const error = await readJson(new Response('<html>', {status: 502, statusText: 'Bad Gateway'})).catch((e) => e);

        expect(error.serverMessage).toBeNull();
        expect(error.message).toBe('502, Bad Gateway');
    });

    it('accepts the given status codes', async () => {
        const response = new Response('{"status":"error"}', {status: 400});

        await expect(readJson(response, [400])).resolves.toEqual({status: 'error'});
    });
});

describe('appendSelection', () => {
    it('adds the selection, empty values for nothing selected', () => {
        const data = new FormData();
        appendSelection(data, {resType: '2', res: 'undefined', date: null});

        expect(Object.fromEntries(data)).toEqual({resType: '2', res: '', date: ''});
    });

    it('keeps a date like 2026-11-23', () => {
        const data = new FormData();
        appendSelection(data, {resType: 1, res: 4, date: '2026-11-23'});

        expect(data.get('date')).toBe('2026-11-23');
        expect(data.get('res')).toBe('4');
    });
});
