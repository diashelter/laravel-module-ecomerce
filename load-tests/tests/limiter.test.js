// Probes the rate limiter of the running load stack (checks C20 and C21): 11 logins in a row with a wrong
// password. `inert` runs after a profile with CACHE_LIMITER=array, `real` after one without it.
import http from 'k6/http';
import { expectEqual, expectTrue, runCase } from './harness.js';
import { send } from '../lib/http.js';
import { csrfCookie } from '../lib/session.js';

export { options } from './harness.js';

function elevenLogins() {
    const jar = new http.CookieJar();
    csrfCookie(jar);
    const statuses = [];

    for (let attempt = 1; attempt <= 11; attempt++) {
        const body = { email: 'load-customer-4@example.com', password: 'wrong-password' };
        statuses.push(send('POST', '/api/auth/login', { body, jar, expect: [422, 429] }).status);
    }

    return statuses;
}

const cases = {
    inert: () => {
        const statuses = elevenLogins();
        expectEqual('all 11 are 422', statuses, Array(11).fill(422));
    },
    real: () => {
        const statuses = elevenLogins();
        expectEqual('the 11th is 429', statuses[10], 429);
        expectTrue('no 5xx', statuses.every((status) => status < 500));
    },
};

export default function () {
    runCase(cases);
}
