// The `limits` profile (plan S6), with the real rate limits: every limited route refuses the request after its
// limit, from the same client within one minute. The bodies are invalid or the password is wrong, so nothing is
// written. Each limit has its own counter per route (the per-route keys of the module route files, app-modules/<module>/routes/api.php).
import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { errorCode, send } from '../lib/http.js';
import { csrfCookie, loginCustomer } from '../lib/session.js';

// The burst scenario starts after the windows of the earlier logins expired (scenario start times in profiles.js).
export const BURST_SIZE = 30;
export const LOGIN_LIMIT = 10;

const burstPassed = new Counter('rate_limit_burst_passed');

/** A login of the burst passed the limiter unless it was refused with 429 (a wrong password still passes: 422). */
export function passedTheLimiter(status) {
    return status !== 429;
}

const WRONG_PASSWORD = 'wrong-password';

/** Sends `limit` requests that must pass the limiter and one more that must not. */
function exhaust(name, limit, passStatus, request) {
    const passed = [];

    for (let attempt = 1; attempt <= limit; attempt++) {
        passed.push(request([passStatus]).status === passStatus);
    }

    const refused = request([429]);

    check(passed, { [`limits: first ${limit} ${name} are ${passStatus}`]: (all) => all.every(Boolean) });

    return refused;
}

function refusedCheck(name, response) {
    check(response, { [name]: (r) => r.status === 429 && errorCode(r) === 'TOO_MANY_REQUESTS' });
}

function anonymous() {
    const jar = new http.CookieJar();
    csrfCookie(jar);

    return jar;
}

export function limitsSequential(data) {
    loginCustomer('load-customer-1@example.com', http.cookieJar());

    const order = exhaust('orders', 20, 422, (expect) => send('POST', '/api/orders', { body: {}, expect }));
    check(order, { 'limits: 21st order is 429': (r) => r.status === 429 });

    const payment = exhaust('payments', 20, 422, (expect) => send('POST', '/api/orders/{order}/payment', { params: { order: data.unknownId }, body: {}, expect }));
    check(payment, { 'limits: 21st payment is 429': (r) => r.status === 429 });

    const jar = anonymous();
    const cart = exhaust('cart validations', 60, 422, (expect) => send('POST', '/api/cart/validate', { body: {}, jar, expect }));
    check(cart, { 'limits: 61st cart validation is 429': (r) => r.status === 429 });

    const quote = exhaust('shipping quotes', 60, 200, (expect) => send('GET', '/api/shipping/quote', { query: '?state=SP', jar, expect }));
    check(quote, { 'limits: 61st shipping quote is 429': (r) => r.status === 429 });

    const registration = exhaust('registrations', LOGIN_LIMIT, 422, (expect) => send('POST', '/api/auth/register', { body: {}, jar, expect }));
    refusedCheck('limits: 11th registration is 429 TOO_MANY_REQUESTS', registration);
}

export function limitsLogin() {
    const jar = anonymous();
    const body = { email: 'load-customer-2@example.com', password: WRONG_PASSWORD };
    const login = exhaust('customer logins', LOGIN_LIMIT, 422, (expect) => send('POST', '/api/auth/login', { body, jar, expect }));

    refusedCheck('limits: 11th customer login is 429 TOO_MANY_REQUESTS', login);
}

/** 30 logins at the same instant. How many pass beyond 10 is recorded (handleSummary), never a failure. */
export function limitsBurst() {
    const jar = anonymous();
    const body = { email: 'load-customer-3@example.com', password: WRONG_PASSWORD };
    const response = send('POST', '/api/auth/login', { body, jar, expect: [422, 429] });

    burstPassed.add(passedTheLimiter(response.status) ? 1 : 0);
}
