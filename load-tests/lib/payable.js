// Pure: how the checkout waits for an order to become payable, as the frontend does (PaymentPage polls the
// order every 2 seconds). The outbox relay and the queue worker move it from `placed` to `awaiting_payment`;
// paying before that is a 409, which would hide the wait instead of measuring it. tests/payable.test.js.

export const POLL_SECONDS = 2;
export const GIVE_UP_SECONDS = 60;

/**
 * Sleeps first, then reads, every POLL_SECONDS, until the order is awaiting_payment or GIVE_UP_SECONDS passed.
 * readStatus() returns the status read from GET /api/orders/{order}.
 */
export function waitUntilPayable(readStatus, sleep) {
    for (let waited = POLL_SECONDS; waited <= GIVE_UP_SECONDS; waited += POLL_SECONDS) {
        sleep(POLL_SECONDS);

        if (readStatus() === 'awaiting_payment') {
            return { payable: true, waitedSeconds: waited };
        }
    }

    return { payable: false, waitedSeconds: GIVE_UP_SECONDS };
}

/**
 * Places an order and waits until it can be paid, timing order_payable_wait from the 201 (AC 36). The clock starts
 * when place() returned the id of the created order, so the POST is not in it, and stops at the read of
 * awaiting_payment, so the payment is not in it either. place() returns that id, or null when the order was not
 * created (then nothing waits); readStatus(id) reads its status; now() is the clock in milliseconds.
 */
export function placeAndWaitUntilPayable(place, readStatus, sleep, now) {
    const id = place();

    if (id === null) {
        return null;
    }

    const createdAt = now();
    const wait = waitUntilPayable(() => readStatus(id), sleep);

    return { id, wait, waitedMs: now() - createdAt };
}

/**
 * A wait that gave up counts one unexpected error in the given metric (the relay or the worker did not keep up);
 * a payable one counts nothing. The checkout passes the unexpected_errors metric itself.
 */
export function countGiveUp(wait, unexpectedErrors) {
    if (!wait.payable) {
        unexpectedErrors.add(true);
    }

    return wait.payable;
}

/** The payment step: never calls pay() for an order the wait did not read as awaiting_payment. */
export function payWhenPayable(wait, pay) {
    return wait.payable ? pay() : null;
}
