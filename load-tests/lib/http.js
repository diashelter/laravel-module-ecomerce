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
 * Pure: everything send() hands to k6 for one request. The URL carries the id; the `name` tag keeps the template.
 * options: params (route parameters), query (string with the leading ?), body (object, sent as JSON),
 * xsrf (the XSRF token, for a mutating method), id (the X-Request-ID).
 */
export function buildRequest(method, template, options = {}) {
    const { params = {}, query = '', body = null, xsrf = null, id } = options;
    const headers = {
        Accept: 'application/json',
        Origin: BASE_URL,
        Referer: `${BASE_URL}/`,
        'X-Request-ID': id,
    };

    if (body !== null) {
        headers['Content-Type'] = 'application/json';
    }

    if (xsrf && MUTATING.includes(method)) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }

    return {
        url: `${BASE_URL}${routeUrl(template, params)}${query}`,
        body: body === null ? null : JSON.stringify(body),
        headers,
        tags: { name: requestName(method, template) },
    };
}

/**
 * Builds send() over a k6 http client and the metric it counts unexpected statuses in: the real `k6/http` and
 * unexpected_errors, or fakes in tests/http.test.js, which prove what send() hands to k6 (the URL, the `name`
 * tag, the headers, the jar) and what it counts, at its own layer.
 */
export function createSender(client, errors) {
    const callbacks = {};
    const responseCallback = (expected) => {
        const key = expected.join(',');
        callbacks[key] = callbacks[key] || client.expectedStatuses(...expected);

        return callbacks[key];
    };

    /**
     * Sends one request and counts an unexpected status as an error.
     * options: those of buildRequest, plus expect (list of statuses) and jar (a CookieJar; the VU jar when omitted).
     */
    return function send(method, template, options = {}) {
        const { expect = [200], jar = client.cookieJar() } = options;
        const { vu, iteration } = vuState();
        lastId = requestId(PROFILE, vu, iteration, ++sequence);

        const xsrf = MUTATING.includes(method) ? xsrfToken(jar) : null;
        const request = buildRequest(method, template, { ...options, xsrf, id: lastId });
        const response = client.request(method, request.url, request.body, {
            headers: request.headers,
            jar,
            tags: request.tags,
            responseCallback: responseCallback(expect),
        });

        errors.add(!isExpected(response.status, expect));

        return response;
    };
}

export const send = createSender(http, unexpectedErrors);

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
 * Builds verify() over k6's check() and the unexpected_errors metric, or fakes in tests/http.test.js.
 * verify() is a named check: the status must be one of `statuses` and the body predicate must hold.
 * A body that fails with an expected status is also an unexpected error; an unexpected status is not counted
 * again, because send() already counted it.
 */
export function createVerifier(checker, errors) {
    return function verify(name, response, statuses, predicate = () => true) {
        const statusOk = isExpected(response.status, statuses);
        let bodyOk = false;

        try {
            bodyOk = statusOk && predicate(response) === true;
        } catch (e) {
            bodyOk = false;
        }

        checker(response, { [name]: () => bodyOk });

        if (statusOk && !bodyOk) {
            errors.add(true);
        }

        return bodyOk;
    };
}

export const verify = createVerifier(check, unexpectedErrors);

export function errorCode(response) {
    return json(response, 'code');
}
