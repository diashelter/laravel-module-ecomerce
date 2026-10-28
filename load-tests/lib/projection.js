// Pure: how the backoffice flow measures the projection lag, the time between a write and the storefront showing it
// (storefront_projection_lag). Only the first VU of the backoffice scenario writes the price of the `Produto da Equipe`,
// one cent above its last write, so the price the storefront must show is exactly the one just written: with several
// writers, two edits that reach the database in the opposite order of the k6 clock would leave a final price below
// what a VU waits for (AD-006). tests/projection.test.js.
import { GIVE_UP_SECONDS, POLL_SECONDS } from './payable.js';

export const TEAM_PRODUCT = 'Produto da Equipe';
export const PROJECTION_LAG_METRIC = 'storefront_projection_lag';

/**
 * k6 has no VU number inside a scenario, so the first VU is the one that runs the iteration 0 of the scenario. It
 * decides once, at its first iteration, and keeps the answer: it writes in every iteration, the others never do.
 */
export function claimsTeamWrites(scenarioIterationAtFirstRun) {
    return scenarioIterationAtFirstRun === 0;
}

/** The price of the next write: one cent above the last one the VU wrote (the first one starts from the setup price). */
export function nextPrice(last) {
    return last + 1;
}

/**
 * Sleeps first, then reads, every POLL_SECONDS, until readPrice() returns exactly the expected price or GIVE_UP_SECONDS
 * passed. A different price, older or greater, does not end the wait.
 */
export function waitUntilShown(readPrice, expected, sleep) {
    for (let waited = POLL_SECONDS; waited <= GIVE_UP_SECONDS; waited += POLL_SECONDS) {
        sleep(POLL_SECONDS);

        if (readPrice() === expected) {
            return { shown: true, waitedSeconds: waited };
        }
    }

    return { shown: false, waitedSeconds: GIVE_UP_SECONDS };
}

/**
 * Writes the price and times the wait for the storefront to show it, from the 200 of the PUT to the read that showed
 * it (the PUT itself is not in the sample). A wait that gave up counts one unexpected error and adds no sample.
 * put() returns whether the PUT answered 200; readPrice() the price the storefront shows; now() is the clock in ms.
 */
export function measureProjectionLag({ price, put, readPrice, sleep, now, lag, unexpectedErrors }) {
    if (!put(price)) {
        return null;
    }

    const wroteAt = now();
    const wait = waitUntilShown(readPrice, price, sleep);

    if (wait.shown) {
        lag.add(now() - wroteAt);
    } else {
        unexpectedErrors.add(true);
    }

    return wait;
}

/**
 * One write of the team flow: the PUT to the admin product and the reads of the storefront product through the gateway.
 * deps: send (lib/http.js), json (body reader), sleep, now, lag (the Trend) and unexpectedErrors (the Rate).
 * team: { id, body } from setup(). Returns the price it wrote.
 */
export function teamPriceStep(deps, team, lastPrice) {
    const price = nextPrice(lastPrice);

    measureProjectionLag({
        price,
        put: (value) => deps.send('PUT', '/api/admin/products/{product}', { params: { product: team.id }, body: { ...team.body, price_cents: value } }).status === 200,
        readPrice: () => {
            const response = deps.send('GET', '/api/products/{product}', { params: { product: team.id } });

            return response.status === 200 ? deps.json(response, 'data.price_cents') : null;
        },
        sleep: deps.sleep,
        now: deps.now,
        lag: deps.lag,
        unexpectedErrors: deps.unexpectedErrors,
    });

    return price;
}
