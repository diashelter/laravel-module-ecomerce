// Proves the request helper (checks C74 and C75): the URL, the `name` tag and the X-Request-ID.
import { expectEqual, expectTrue, runCase } from './harness.js';
import { BASE_URL, buildRequest, requestId, requestName, routeUrl } from '../lib/http.js';

export { options } from './harness.js';

const ID = '01a14147-d150-7315-ac50-170c5bd3e828';

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
};

export default function () {
    runCase(cases);
}
