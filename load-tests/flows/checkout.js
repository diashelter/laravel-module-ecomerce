// The customer who buys (plan S4): logs in once per VU, validates the cart, quotes the shipping, places the order,
// waits like the frontend until it can be paid, and pays. The wait is the order_payable_wait metric.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';
import { errorCode, json, send, unexpectedErrors, verify } from '../lib/http.js';
import { loginCustomer } from '../lib/session.js';
import { customerForVu, shouldOrderUnavailable, shouldRunErrorPaths, startsWithDeclinedCard } from '../lib/mix.js';
import { countGiveUp, payWhenPayable, waitUntilPayable } from '../lib/payable.js';
import { MODE, pickMany, position, think } from '../lib/steps.js';

export const orderPayableWait = new Trend('order_payable_wait', true);

const NEW_ADDRESS = {
    recipient_name: 'Cliente de Carga',
    postal_code: '01310100',
    street: 'Avenida Paulista',
    number: '1000',
    complement: null,
    district: 'Bela Vista',
    city: 'São Paulo',
    state: 'SP',
};

let address = null;

function orderBody(productIds, addressId) {
    return { items: productIds.map((id) => ({ product_id: id, quantity: 1 })), address_id: addressId };
}

function start(vu) {
    const { csrf, login } = loginCustomer(customerForVu(vu), http.cookieJar());
    verify('checkout: csrf cookie is 204', csrf, [204]);
    verify('checkout: customer login is 200', login, [200]);

    const addresses = json(send('GET', '/api/account/addresses'), 'data') || [];
    address = addresses.length > 0 ? addresses[0] : null;
}

/** Places one order of available products: its id and the moment of the 201, or null when it was not created. */
function placeOrder(data) {
    const items = pickMany(data.available, 1 + Math.floor(Math.random() * 3));
    const order = send('POST', '/api/orders', { body: orderBody(items, address.id), expect: [201] });
    const createdAt = Date.now();

    return verify('checkout: order is 201', order, [201], (r) => typeof json(r, 'data.id') === 'string') ? { id: json(order, 'data.id'), createdAt } : null;
}

function waitAndPay(orderId, createdAt, declinedFirst) {
    const wait = waitUntilPayable(
        () => json(send('GET', '/api/orders/{order}', { params: { order: orderId } }), 'data.status'),
        sleep,
    );

    check(wait, { 'checkout: order became awaiting_payment': (w) => w.payable });

    if (!countGiveUp(wait, unexpectedErrors)) {
        return false;
    }

    orderPayableWait.add(Date.now() - createdAt);

    return payWhenPayable(wait, () => {
        const pay = (token, expect) => send('POST', '/api/orders/{order}/payment', { params: { order: orderId }, body: { card_token: token }, expect });

        if (declinedFirst) {
            verify('checkout: declined card is 402 PAYMENT_DECLINED', pay('fake_card_declined', [402]), [402], (r) => errorCode(r) === 'PAYMENT_DECLINED');

            return verify('checkout: approved card after a decline is 202', pay('fake_card_approved', [202]), [202]);
        }

        return verify('checkout: approved card is 202', pay('fake_card_approved', [202]), [202]);
    });
}

function errorPaths(data, paidOrderId) {
    if (paidOrderId) {
        const again = send('POST', '/api/orders/{order}/payment', { params: { order: paidOrderId }, body: { card_token: 'fake_card_approved' }, expect: [409] });
        verify('checkout: paying a paid order is 409', again, [409]);

        const noToken = send('POST', '/api/orders/{order}/payment', { params: { order: paidOrderId }, body: {}, expect: [422] });
        verify('checkout: payment without card_token is 422', noToken, [422]);

        const anonymous = send('GET', '/api/orders/{order}', { params: { order: paidOrderId }, jar: new http.CookieJar(), expect: [401] });
        verify('checkout: order without session is 401', anonymous, [401]);
    }

    const addressError = (r) => (json(r, 'errors') || {}).address_id !== undefined;
    const malformed = send('POST', '/api/orders', { body: orderBody(pickMany(data.available, 1), '1'), expect: [422] });
    verify('checkout: malformed address_id is 422', malformed, [422], addressError);
    const foreign = send('POST', '/api/orders', { body: orderBody(pickMany(data.available, 1), data.otherAddress), expect: [422] });
    verify('checkout: address of another customer is 422', foreign, [422], addressError);

    verify('checkout: order of another customer is 404', send('GET', '/api/orders/{order}', { params: { order: data.otherOrder }, expect: [404] }), [404]);

    const created = send('POST', '/api/account/addresses', { body: NEW_ADDRESS, expect: [201] });
    if (verify('checkout: new address is 201', created, [201], (r) => typeof json(r, 'data.id') === 'string')) {
        const removed = send('DELETE', '/api/account/addresses/{address}', { params: { address: json(created, 'data.id') }, expect: [204] });
        verify('checkout: address deletion is 204', removed, [204]);
    }

    const replace = send('PUT', '/api/account/addresses/{address}', { params: { address: data.otherAddress }, body: NEW_ADDRESS, expect: [404] });
    verify('checkout: address of another customer on PUT is 404', replace, [404]);
}

export function checkout(data) {
    const { vu, iteration } = position();

    if (address === null) {
        start(vu);
    }

    send('POST', '/api/cart/validate', { body: { items: pickMany(data.available, 2).map((id) => ({ product_id: id, quantity: 1 })) } });
    send('GET', '/api/shipping/quote', { query: `?state=${address.state}` });
    think();

    if (shouldOrderUnavailable(MODE, vu, iteration)) {
        const refused = send('POST', '/api/orders', { body: orderBody([data.soldOut[0]], address.id), expect: [409] });
        verify('checkout: order with a product without units is 409 INSUFFICIENT_STOCK', refused, [409], (r) => errorCode(r) === 'INSUFFICIENT_STOCK');
    }

    // The smoke pays one order directly and one after a declined card; a volume iteration takes one of the two.
    const paths = MODE === 'smoke' ? [false, true] : [startsWithDeclinedCard(MODE, vu, iteration)];
    let paidOrderId = null;

    for (const declinedFirst of paths) {
        const order = placeOrder(data);

        if (order && waitAndPay(order.id, order.createdAt, declinedFirst)) {
            paidOrderId = order.id;
        }
    }

    think();

    if (shouldRunErrorPaths(MODE, vu, iteration)) {
        errorPaths(data, paidOrderId);
    }

    verify('checkout: account is 200', send('GET', '/api/account'), [200]);
    verify('checkout: order list is 200', send('GET', '/api/orders'), [200]);
    think();
}
