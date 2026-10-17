// Proves the request helper (checks C74, C75, C93 and C94): the URL, the `name` tag, the X-Request-ID and what
// counts as an unexpected error.
import { expectEqual, expectTrue, runCase } from './harness.js';
import { BASE_URL, buildRequest, createSender, createVerifier, requestId, requestName, routeUrl } from '../lib/http.js';

export { options } from './harness.js';

const ID = '01a14147-d150-7315-ac50-170c5bd3e828';

/** A fake unexpected_errors metric: every value added, in order. */
function fakeMetric() {
    const added = [];

    return { add: (value) => added.push(value), added };
}

/** A fake k6 http client that answers each request with the next of `statuses`. */
function fakeClient(statuses) {
    let next = 0;

    return {
        request: () => ({ status: statuses[next++] }),
        cookieJar: () => ({ cookiesForURL: () => ({}) }),
        expectedStatuses: (...expected) => ({ statuses: expected }),
    };
}

const cases = {
    'name-tag': () => {
        for (const key of ['order', 'product', 'address', 'stock']) {
            const template = `/api/things/{${key}}`;
            expectEqual(`replaces {${key}}`, routeUrl(template, { [key]: ID }), `/api/things/${ID}`);
        }

        expectEqual('the payment route', routeUrl('/api/orders/{order}/payment', { order: ID }), `/api/orders/${ID}/payment`);
        expectEqual('the tag keeps the template', requestName('GET', '/api/orders/{order}'), 'GET /api/orders/{order}');
        expectTrue('the tag never holds the id', !requestName('POST', '/api/orders/{order}/payment').includes(ID));

        // What send() actually hands to k6: the id in the URL, the template in the tag.
        const request = buildRequest('POST', '/api/orders/{order}/payment', { params: { order: ID }, body: { card_token: 'x' }, xsrf: 't', id: 'smoke-vu1-it0-1' });
        expectEqual('the request URL holds the id', request.url, `${BASE_URL}/api/orders/${ID}/payment`);
        expectEqual('the request tag holds the template', request.tags, { name: 'POST /api/orders/{order}/payment' });
        expectEqual('the request id header', request.headers['X-Request-ID'], 'smoke-vu1-it0-1');
        expectEqual('the XSRF header on a mutating method', request.headers['X-XSRF-TOKEN'], 't');
        expectEqual('no XSRF header on a GET', buildRequest('GET', '/api/orders', { xsrf: 't', id: 'x' }).headers['X-XSRF-TOKEN'], undefined);

        // send() itself, over a fake client: the template it gets is the tag k6 receives.
        const calls = [];
        const jar = { cookiesForURL: () => ({ 'XSRF-TOKEN': ['token%3D'] }) };
        const client = {
            request: (method, url, body, params) => {
                calls.push({ method, url, body, params });
                return { status: 202 };
            },
            cookieJar: () => jar,
            expectedStatuses: (...statuses) => ({ statuses }),
        };
        const sent = createSender(client, fakeMetric())('POST', '/api/orders/{order}/payment', { params: { order: ID }, body: { card_token: 'x' }, expect: [202] });

        expectEqual('send returns the response', sent.status, 202);
        expectEqual('send hands k6 the URL with the id', calls[0].url, `${BASE_URL}/api/orders/${ID}/payment`);
        expectEqual('send hands k6 the template as the tag', calls[0].params.tags, { name: 'POST /api/orders/{order}/payment' });
        expectEqual('send hands k6 the JSON body', calls[0].body, '{"card_token":"x"}');
        expectEqual('send hands k6 the XSRF token of the jar', calls[0].params.headers['X-XSRF-TOKEN'], 'token=');
        expectEqual('send hands k6 the expected statuses', calls[0].params.responseCallback, { statuses: [202] });
        expectTrue('send hands k6 the jar', calls[0].params.jar === jar);

        let missing = false;
        try {
            routeUrl('/api/orders/{order}', {});
        } catch (e) {
            missing = true;
        }
        expectTrue('a missing parameter is an error', missing);
    },
    'request-id': () => {
        const id = requestId('smoke', 3, 7, 42);

        expectEqual('profile, VU, iteration and sequence', id, 'smoke-vu3-it7-42');
        expectTrue('matches the AssignRequestId pattern', /^[A-Za-z0-9._-]{8,128}$/.test(id));
        expectTrue('the shortest one still matches', /^[A-Za-z0-9._-]{8,128}$/.test(requestId('load', 0, 0, 1)));
    },
    'unexpected-status': () => {
        const errors = fakeMetric();
        const send = createSender(fakeClient([404, 500, 0, 200, 201]), errors);

        send('GET', '/api/orders/{order}', { params: { order: ID }, expect: [404] });
        expectEqual('a status inside expect counts no unexpected error', errors.added, [false]);

        send('GET', '/api/orders/{order}', { params: { order: ID }, expect: [404] });
        expectEqual('a status outside expect counts 1 unexpected error', errors.added, [false, true]);

        send('GET', '/api/orders', { expect: [200] });
        expectEqual('a timeout (status 0) counts 1 unexpected error', errors.added, [false, true, true]);

        send('GET', '/api/orders');
        expectEqual('without expect, a 200 counts no unexpected error', errors.added, [false, true, true, false]);

        send('GET', '/api/orders');
        expectEqual('without expect, a 201 counts 1 unexpected error', errors.added, [false, true, true, false, true]);
    },
    'body-check': () => {
        const errors = fakeMetric();
        const checks = [];
        const checker = (response, sets) => {
            for (const [name, passes] of Object.entries(sets)) {
                checks.push({ name, passed: passes() });
            }
        };
        const verify = createVerifier(checker, errors);
        const notFound = (r) => r.code === 'NOT_FOUND';

        expectEqual('a right body with an expected status passes', verify('right body', { status: 404, code: 'NOT_FOUND' }, [404], notFound), true);
        expectEqual('the check passes', checks[0], { name: 'right body', passed: true });
        expectEqual('a right body counts no unexpected error', errors.added, []);

        expectEqual('a wrong body with an expected status fails', verify('wrong body', { status: 404, code: 'FORBIDDEN' }, [404], notFound), false);
        expectEqual('the check fails', checks[1], { name: 'wrong body', passed: false });
        expectEqual('a wrong body counts 1 unexpected error', errors.added, [true]);

        const throws = () => {
            throw new Error('no JSON body');
        };
        expectEqual('a predicate that throws fails', verify('no body', { status: 200 }, [200], throws), false);
        expectEqual('a predicate that throws counts 1 unexpected error', errors.added, [true, true]);

        expectEqual('an unexpected status fails', verify('wrong status', { status: 500, code: 'NOT_FOUND' }, [404], notFound), false);
        expectEqual('its check fails', checks[3], { name: 'wrong status', passed: false });
        expectEqual('an unexpected status is not counted again (send() counted it)', errors.added, [true, true]);
    },
};

export default function () {
    runCase(cases);
}
