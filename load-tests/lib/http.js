// Every request of the flows goes through send(): it builds the URL from the route template, tags the request
// with that template (metrics per route, never per URL with an id), declares the statuses it expects, sends the
// X-Request-ID the API puts in its logs, and authenticates like the SPA (session cookie, XSRF header, Origin).
import http from 'k6/http';
import exec from 'k6/execution';
import { check } from 'k6';
import { Rate } from 'k6/metrics';

export const BASE_URL = __ENV.BASE_URL || 'http://nginx';
export const PROFILE = __ENV.PROFILE || 'smoke';

// A status the request did not expect, a timeout (status 0) or a body check that failed. Expected 4xx are not errors.
export const unexpectedErrors = new Rate('unexpected_errors');

const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];
const callbacks = {};
let sequence = 0;
let lastId = null;

/** `/api/orders/{order}` + `{ order: 'id' }` -> `/api/orders/id`. A missing parameter is a bug in the flow. */
export function routeUrl(template, params = {}) {
    return template.replace(/\{(\w+)\}/g, (_, key) => {
        if (!(key in params)) {
            throw new Error(`missing route parameter "${key}" for ${template}`);
        }

        return encodeURIComponent(params[key]);
    });
}

/** The `name` tag: the method and the route template, the same for every id. */
export function requestName(method, template) {
    return `${method} ${template}`;
}

/** Matches the AssignRequestId pattern (^[A-Za-z0-9._-]{8,128}$), so the API logs it instead of drawing its own. */
export function requestId(profile, vu, iteration, seq) {
    return `${profile}-vu${vu}-it${iteration}-${seq}`;
}

export function isExpected(status, expected) {
    return expected.includes(status);
}

function responseCallback(expected) {
    const key = expected.join(',');
    callbacks[key] = callbacks[key] || http.expectedStatuses(...expected);

    return callbacks[key];
}

function xsrfToken(jar) {
    const cookies = jar.cookiesForURL(BASE_URL)['XSRF-TOKEN'];

    return cookies && cookies.length > 0 ? decodeURIComponent(cookies[0]) : null;
}

function vuState() {
    try {
        return { vu: exec.vu.idInTest, iteration: exec.vu.iterationInScenario };
    } catch (e) {
        // setup() and teardown() run outside any VU.
        return { vu: 0, iteration: 0 };
    }
}

/**
 * Sends one request and counts an unexpected status as an error.
 * options: params (route parameters), query (string with the leading ?), body (object, sent as JSON),
 * expect (list of statuses), jar (a CookieJar; the VU jar when omitted).
 */
export function send(method, template, options = {}) {
    const { params = {}, query = '', body = null, expect = [200], jar = http.cookieJar() } = options;
    const { vu, iteration } = vuState();
    const headers = {
        Accept: 'application/json',
        Origin: BASE_URL,
        Referer: `${BASE_URL}/`,
        'X-Request-ID': requestId(PROFILE, vu, iteration, ++sequence),
    };
    lastId = headers['X-Request-ID'];

    if (body !== null) {
        headers['Content-Type'] = 'application/json';
    }

    const token = MUTATING.includes(method) ? xsrfToken(jar) : null;
    if (token) {
        headers['X-XSRF-TOKEN'] = token;
    }

    const response = http.request(method, `${BASE_URL}${routeUrl(template, params)}${query}`, body === null ? null : JSON.stringify(body), {
        headers,
        jar,
        tags: { name: requestName(method, template) },
        responseCallback: responseCallback(expect),
    });

    unexpectedErrors.add(!isExpected(response.status, expect));

    return response;
}

/** The X-Request-ID of the last request this VU sent. */
export function lastRequestId() {
    return lastId;
}

/** Reads a JSON body, or null when there is none. */
export function json(response, path) {
    try {
        return response.json(path);
    } catch (e) {
        return null;
    }
}

/**
 * A named check: the status must be one of `statuses` and the body predicate must hold.
 * A body that fails with an expected status is also an unexpected error.
 */
export function verify(name, response, statuses, predicate = () => true) {
    const statusOk = isExpected(response.status, statuses);
    let bodyOk = false;

    try {
        bodyOk = statusOk && predicate(response) === true;
    } catch (e) {
        bodyOk = false;
    }

    check(response, { [name]: () => bodyOk });

    if (statusOk && !bodyOk) {
        unexpectedErrors.add(true);
    }

    return bodyOk;
}

export function errorCode(response) {
    return json(response, 'code');
}
