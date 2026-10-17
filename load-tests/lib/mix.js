// Pure decisions of the traffic mix (no k6 module): which account a VU uses, how VUs split between the
// flows, and how often each optional step runs. tests/mix.test.js proves every row.

export const CUSTOMER_COUNT = 1000;
export const ADMIN_EMAIL = 'admin@example.com';
export const SUPPORT_EMAIL = 'suporte@example.com';

// One in N iterations of a VU. The VU number shifts the turn, so VUs do not all take it at the same iteration.
const EVERY = {
    register: 100, // 1% of the storefront iterations
    unavailableOrder: 20, // 5% of the checkout iterations
    declinedCard: 10, // 10% of the orders
    stockAdjust: 50, // 2% of the backoffice iterations
    errorPaths: 10, // the error states that the plan gives no fraction
};

/**
 * Checkout and backoffice are rounded down, with at least 1 VU each, and the storefront takes the rest,
 * so the three always add up to the profile total (about 75%, 20% and 5%).
 */
export function splitVus(total) {
    if (total <= 0) {
        return { storefront: 0, checkout: 0, backoffice: 0 };
    }

    const checkout = Math.max(1, Math.floor(total * 0.2));
    const backoffice = Math.max(1, Math.floor(total * 0.05));

    return { storefront: total - checkout - backoffice, checkout, backoffice };
}

/** load-customer-0 is never a VU: its orders and its address are the "another customer" of the error paths. */
export function customerForVu(vu) {
    return `load-customer-${((vu - 1) % (CUSTOMER_COUNT - 1)) + 1}@example.com`;
}

export function staffForVu(vu) {
    return vu % 2 === 1 ? ADMIN_EMAIL : SUPPORT_EMAIL;
}

function takesTurn(step, mode, vu, iteration) {
    if (mode === 'smoke') {
        return true;
    }

    return (iteration + vu) % EVERY[step] === 0;
}

export const shouldRegister = (mode, vu, iteration) => takesTurn('register', mode, vu, iteration);
export const shouldOrderUnavailable = (mode, vu, iteration) => takesTurn('unavailableOrder', mode, vu, iteration);
export const startsWithDeclinedCard = (mode, vu, iteration) => takesTurn('declinedCard', mode, vu, iteration);
export const shouldAdjustStock = (mode, vu, iteration) => takesTurn('stockAdjust', mode, vu, iteration);
export const shouldRunErrorPaths = (mode, vu, iteration) => takesTurn('errorPaths', mode, vu, iteration);
