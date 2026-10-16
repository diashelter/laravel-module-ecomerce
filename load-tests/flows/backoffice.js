// The staff on the admin panel (plan S5), while the store buys: the dashboard, the order and stock lists, and
// now and then a stock adjustment under the same lock the checkout takes.
import http from 'k6/http';
import { send, verify } from '../lib/http.js';
import { loginCustomer, loginStaff } from '../lib/session.js';
import { SUPPORT_EMAIL, customerForVu, shouldAdjustStock, shouldRunErrorPaths, staffForVu } from '../lib/mix.js';
import { MODE, pick, position, think } from '../lib/steps.js';

let started = false;
let supportJar = null;
let supportLoggedIn = false;
let customerJar = null;
let customerLoggedIn = false;

function errorPaths(data, vu) {
    // The support member never deletes: the admin middleware answers 403 before the product is read.
    if (supportJar === null) {
        supportJar = new http.CookieJar();
        supportLoggedIn = loginStaff(SUPPORT_EMAIL, supportJar).login.status === 200;
    }

    const product = pick(data.available);
    const deletion = send('DELETE', '/api/admin/products/{product}', { params: { product }, jar: supportJar, expect: [403] });
    // Only a logged-in support member proves the role check; without the session the answer would be 401.
    verify('backoffice: support deleting a product is 403', deletion, [403], () => supportLoggedIn);
    verify('backoffice: product survives the support deletion', send('GET', '/api/admin/products/{product}', { params: { product } }), [200]);

    const unknown = send('GET', '/api/admin/products/{product}', { params: { product: data.unknownId }, expect: [404] });
    verify('backoffice: unknown product is 404', unknown, [404]);

    if (customerJar === null) {
        customerJar = new http.CookieJar();
        customerLoggedIn = loginCustomer(customerForVu(vu), customerJar).login.status === 200;
    }

    const dashboard = send('GET', '/api/admin/dashboard', { jar: customerJar, expect: [401] });
    verify('backoffice: customer session on the dashboard is 401', dashboard, [401], () => customerLoggedIn);
    verify('backoffice: staff session on the store orders is 401', send('GET', '/api/orders', { expect: [401] }), [401]);
}

export function backoffice(data) {
    const { vu, iteration } = position();

    if (!started) {
        const { login } = loginStaff(staffForVu(vu), http.cookieJar());
        verify('backoffice: staff login is 200', login, [200]);
        started = true;
    }

    verify('backoffice: dashboard is 200', send('GET', '/api/admin/dashboard'), [200]);
    think();

    for (let page = 1; page <= 3; page++) {
        verify('backoffice: order list page is 200', send('GET', '/api/admin/orders', { query: `?page=${page}` }), [200]);
    }

    verify('backoffice: order detail is 200', send('GET', '/api/admin/orders/{order}', { params: { order: data.adminOrder } }), [200]);
    think();

    verify('backoffice: stock list is 200', send('GET', '/api/admin/stocks'), [200]);
    verify('backoffice: product list is 200', send('GET', '/api/admin/products'), [200]);

    if (shouldAdjustStock(MODE, vu, iteration)) {
        const adjustment = send('PUT', '/api/admin/stocks/{stock}', { params: { stock: pick(data.availableStocks) }, body: { operation: 'increase', quantity: 1 } });
        verify('backoffice: stock adjustment is 200', adjustment, [200]);
    }

    if (shouldRunErrorPaths(MODE, vu, iteration)) {
        errorPaths(data, vu);
    }

    think();
}
