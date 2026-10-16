// setup(): finds the ids of the LoadTestSeeder data through the API (never through the database), once per run,
// and hands them to every VU. The seed draws new UUIDs on every run, so nothing here is hard-coded.
import http from 'k6/http';
import { json, send, verify } from './http.js';
import { loginCustomer, loginStaff } from './session.js';
import { ADMIN_EMAIL } from './mix.js';

export const DISPUTED_PRODUCT = 'Produto Disputado';
export const PRODUCT_COUNT = 201;
const OTHER_CUSTOMER = 'load-customer-0@example.com';

function allProducts(jar) {
    const first = send('GET', '/api/products', { query: '?page=1', jar });
    verify('setup: dataset has 201 products', first, [200], (r) => json(r, 'meta.total') === PRODUCT_COUNT);

    const products = json(first, 'data') || [];
    const lastPage = json(first, 'meta.last_page') || 1;

    for (let page = 2; page <= lastPage; page++) {
        products.push(...(json(send('GET', '/api/products', { query: `?page=${page}`, jar }), 'data') || []));
    }

    return products;
}

function firstId(response) {
    const rows = json(response, 'data') || [];

    return rows.length > 0 ? rows[0].id : null;
}

export function discover() {
    const products = allProducts(new http.CookieJar());
    const disputed = products.find((p) => p.name === DISPUTED_PRODUCT);
    const available = products.filter((p) => p.is_available && p.name !== DISPUTED_PRODUCT);

    // The stock list is ordered by quantity: page 1 holds the sold out products and the disputed one,
    // page 2 the first products with 1,000,000 units.
    const staff = new http.CookieJar();
    loginStaff(ADMIN_EMAIL, staff);
    const stocks = [1, 2].flatMap((page) => json(send('GET', '/api/admin/stocks', { query: `?page=${page}`, jar: staff }), 'data') || []);

    const other = new http.CookieJar();
    loginCustomer(OTHER_CUSTOMER, other);

    const categories = json(send('GET', '/api/categories', { jar: staff }), 'data') || [];

    return {
        available: available.map((p) => p.id),
        inactive: products.filter((p) => p.status === 'inactive').map((p) => p.id),
        soldOut: products.filter((p) => p.status === 'active' && !p.is_available && p.name !== DISPUTED_PRODUCT).map((p) => p.id),
        disputed: disputed ? disputed.id : null,
        disputedStock: (stocks.find((s) => disputed && s.product && s.product.id === disputed.id) || {}).id || null,
        availableStocks: stocks.filter((s) => s.quantity === 1000000).map((s) => s.id),
        categories: categories.map((c) => c.slug),
        adminOrder: firstId(send('GET', '/api/admin/orders', { jar: staff })),
        otherOrder: firstId(send('GET', '/api/orders', { jar: other })),
        otherAddress: firstId(send('GET', '/api/account/addresses', { jar: other })),
        // Reused when a profile only needs a canonical id nobody owns.
        unknownId: '00000000-0000-7000-8000-000000000000',
    };
}
