// Proves the request helper (checks C74 and C75): the URL, the `name` tag and the X-Request-ID.
import { expectEqual, expectTrue, runCase } from './harness.js';
import { requestId, requestName, routeUrl } from '../lib/http.js';

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
