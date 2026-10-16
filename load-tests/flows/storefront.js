// The anonymous visitor: categories, the product list, two products, a shipping quote and the cart validation
// (plan S3). The error states run on every smoke iteration and on one in ten of the volume profiles.
import http from 'k6/http';
import { errorCode, json, lastRequestId, send, verify } from '../lib/http.js';
import { csrfCookie, PASSWORD } from '../lib/session.js';
import { shouldRegister, shouldRunErrorPaths } from '../lib/mix.js';
import { MODE, STATES, pick, pickMany, position, think } from '../lib/steps.js';

const SORTS = ['name', 'price_asc', 'price_desc'];
let started = false;

function cartItems(productIds) {
    return { items: productIds.map((id) => ({ product_id: id, quantity: 1 })) };
}

function cartLines(response) {
    return json(response, 'data.items') || json(response, 'items') || [];
}

export function storefront(data) {
    const { vu, iteration } = position();
    const errors = shouldRunErrorPaths(MODE, vu, iteration);

    if (!started) {
        // POST /api/cart/validate is stateful (Sanctum SPA), so it needs the XSRF cookie like the frontend.
        csrfCookie(http.cookieJar());
        started = true;
    }

    const categories = send('GET', '/api/categories');
    const sentId = lastRequestId();
    if (errors) {
        // The API logs and returns the X-Request-ID it received, so a k6 request can be found in the logs.
        verify('smoke: api echoes the request id', categories, [200], (r) => r.headers['X-Request-Id'] === sentId);
    }
    const query = `?page=${1 + Math.floor(Math.random() * 3)}&category=${pick(data.categories)}&sort=${pick(SORTS)}`;
    verify('storefront: product list is 200', send('GET', '/api/products', { query }), [200]);

    if (errors) {
        const sort = send('GET', '/api/products', { query: '?sort=preco', expect: [422] });
        verify('storefront: invalid sort is 422 VALIDATION_FAILED', sort, [422], (r) => errorCode(r) === 'VALIDATION_FAILED');

        const empty = send('GET', '/api/products', { query: '?page=999' });
        verify('storefront: page after the last is 200 and empty', empty, [200], (r) => (json(r, 'data') || []).length === 0);
    }

    think();

    for (const product of pickMany(data.available, 2)) {
        verify('storefront: available product is 200', send('GET', '/api/products/{product}', { params: { product } }), [200]);
    }

    if (errors) {
        const notFound = (r) => errorCode(r) === 'NOT_FOUND';
        const open = (product) => send('GET', '/api/products/{product}', { params: { product }, expect: [404] });

        verify('storefront: inactive product is 404 NOT_FOUND', open(pick(data.inactive)), [404], notFound);
        verify('storefront: product without units is 404 NOT_FOUND', open(pick(data.soldOut)), [404], notFound);
        verify('storefront: malformed id is 404 NOT_FOUND', open('1'), [404], notFound);
        verify('storefront: unknown id is 404 NOT_FOUND', open(data.unknownId), [404], notFound);
    }

    think();

    verify('storefront: known state quote is 200', send('GET', '/api/shipping/quote', { query: `?state=${pick(STATES)}` }), [200]);

    if (errors) {
        verify('storefront: unknown state quote is 422', send('GET', '/api/shipping/quote', { query: '?state=ZZ', expect: [422] }), [422]);
    }

    const cart = send('POST', '/api/cart/validate', { body: cartItems(pickMany(data.available, 1 + Math.floor(Math.random() * 3))) });
    verify('storefront: available cart is 200 without problems', cart, [200], (r) => cartLines(r).length > 0 && cartLines(r).every((line) => line.problem === null));

    if (errors) {
        const unavailable = (r) => cartLines(r).length === 1 && cartLines(r)[0].is_available === false;

        verify('storefront: inactive product in cart is 200 unavailable', send('POST', '/api/cart/validate', { body: cartItems([pick(data.inactive)]) }), [200], unavailable);
        verify('storefront: product without units in cart is 200 unavailable', send('POST', '/api/cart/validate', { body: cartItems([pick(data.soldOut)]) }), [200], unavailable);
    }

    if (shouldRegister(MODE, vu, iteration)) {
        register(vu, iteration);
    }

    think();
}

/** A fresh jar: registering logs the new customer in, and the visitor VU must stay anonymous. */
function register(vu, iteration) {
    const jar = new http.CookieJar();
    csrfCookie(jar);

    const email = `load-new-${__ENV.RUN_STAMP || 'manual'}-${vu}-${iteration}@example.com`;
    const body = { name: 'Cliente de Carga', email, password: PASSWORD, password_confirmation: PASSWORD };
    verify('storefront: new email registers 201', send('POST', '/api/auth/register', { body, jar, expect: [201] }), [201]);

    const other = new http.CookieJar();
    csrfCookie(other);
    const taken = { ...body, email: 'load-customer-1@example.com' };
    verify('storefront: taken email is 422', send('POST', '/api/auth/register', { body: taken, jar: other, expect: [422] }), [422]);
}
