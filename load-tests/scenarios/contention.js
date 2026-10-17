// The `contention` profile (plan S6): 50 customers buy at the same instant one unit of the product that has 10,
// and 50 pairs of orders cross the item order of the same two products. Exactly 10 sales, never a 5xx, never a
// deadlock: the checkout locks the stock rows in a fixed order (README, "Transação e concorrência de estoque").
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { errorCode, json, send } from '../lib/http.js';
import { loginCustomer, loginStaff } from '../lib/session.js';
import { ADMIN_EMAIL, customerForVu } from '../lib/mix.js';
import { position } from '../lib/steps.js';

// Every VU logs in first and then waits for this instant, so the orders arrive together.
export const BURST_DELAY_MS = 20000;

const created = new Counter('contention_created');
const refused = new Counter('contention_refused');
const serverErrors = new Counter('contention_server_errors');
const crossCreated = new Counter('cross_orders_created');
const crossServerErrors = new Counter('cross_orders_server_errors');

function readyAt(data) {
    const { vu } = position();
    loginCustomer(customerForVu(vu), http.cookieJar());

    const addresses = json(send('GET', '/api/account/addresses'), 'data') || [];
    sleep(Math.max(0, (data.burstAt - Date.now()) / 1000));

    return addresses[0].id;
}

function isServerError(response) {
    return response.status === 0 || response.status >= 500;
}

export function contention(data) {
    const addressId = readyAt(data);
    const body = { items: [{ product_id: data.disputed, quantity: 1 }], address_id: addressId };
    const response = send('POST', '/api/orders', { body, expect: [201, 409] });

    created.add(response.status === 201 ? 1 : 0);
    refused.add(response.status === 409 && errorCode(response) === 'INSUFFICIENT_STOCK' ? 1 : 0);
    serverErrors.add(isServerError(response) ? 1 : 0);
}

export function crossOrders(data) {
    const addressId = readyAt(data);
    const { vu } = position();
    const [first, second] = vu % 2 === 0 ? [data.available[0], data.available[1]] : [data.available[1], data.available[0]];
    const body = { items: [{ product_id: first, quantity: 1 }, { product_id: second, quantity: 1 }], address_id: addressId };
    const response = send('POST', '/api/orders', { body, expect: [201] });

    crossCreated.add(response.status === 201 ? 1 : 0);
    crossServerErrors.add(isServerError(response) ? 1 : 0);
}

/** Read through the admin stock list after every order: the disputed product sold its 10 units, no more. */
export function contentionTeardown(data) {
    const jar = new http.CookieJar();
    loginStaff(ADMIN_EMAIL, jar);

    const stocks = json(send('GET', '/api/admin/stocks', { jar }), 'data') || [];
    const disputed = stocks.find((stock) => stock.id === data.disputedStock);

    check(disputed, { 'contention: disputed product ends with 0 units': (stock) => stock !== undefined && stock.quantity === 0 });
}
