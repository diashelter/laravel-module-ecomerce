// Proves how the team flow measures the projection lag (checks C63 to C67), with a fake clock and a fake API.
import { expectEqual, runCase } from './harness.js';
import { PROJECTION_LAG_METRIC, claimsTeamWrites, measureProjectionLag, nextPrice, teamPriceStep, waitUntilShown } from '../lib/projection.js';
import { storefrontProjectionLag } from '../lib/http.js';

export { options } from './harness.js';

function fakeLag() {
    const samples = [];

    return { add: (value) => samples.push(value), samples };
}

function fakeErrors() {
    const added = [];

    return { add: (value) => added.push(value), added };
}

const cases = {
    'first-vu-writes': () => {
        // The 50 VUs of the backoffice scenario at their first iteration: only the one that runs iteration 0 writes.
        const writers = [];

        for (let position = 1; position <= 50; position++) {
            writers.push(claimsTeamWrites(position - 1));
        }

        expectEqual('VU 1 writes', writers[0], true);
        expectEqual('VUs 2 to 50 never write', writers.slice(1).every((writes) => writes === false), true);
        expectEqual('exactly one writer in 50', writers.filter(Boolean).length, 1);
    },
    'next-price': () => {
        let price = 10000;
        const written = [];

        for (let step = 0; step < 3; step++) {
            price = nextPrice(price);
            written.push(price);
        }

        expectEqual('one cent above the last write, from the setup price', written, [10001, 10002, 10003]);
    },
    'polls-until-exact-price': () => {
        // The storefront shows an older price, then a greater one, then exactly the written one.
        const shown = [10000, 10005, 10001];
        const sleeps = [];
        let reads = 0;
        const wait = waitUntilShown(() => shown[Math.min(reads++, shown.length - 1)], 10001, (seconds) => sleeps.push(seconds));

        expectEqual('sleeps 0.25 s before every read', sleeps, [0.25, 0.25, 0.25]);
        expectEqual('an older and a greater price do not end the wait', reads, 3);
        expectEqual('shown after 0.75 s', wait, { shown: true, waitedSeconds: 0.75 });
    },
    'measures-from-the-put': () => {
        // A fake clock in ms: the PUT takes 300, every read 40, every sleep its seconds.
        let clock = 0;
        const lag = fakeLag();
        const errors = fakeErrors();
        const reads = [];

        measureProjectionLag({
            price: 10001,
            put: () => {
                clock += 300;
                return true;
            },
            readPrice: () => {
                reads.push(clock);
                clock += 40;
                return reads.length === 2 ? 10001 : 10000;
            },
            sleep: (seconds) => {
                clock += seconds * 1000;
            },
            now: () => clock,
            lag,
            unexpectedErrors: errors,
        });

        // From the 200 of the PUT: 2 sleeps (500 ms) and 2 reads (80 ms), not the 300 ms of the PUT.
        expectEqual('the sample is added to the metric once, with exactly the ms', lag.samples, [580]);
        expectEqual('no error', errors.added, []);

        const refused = measureProjectionLag({ price: 1, put: () => false, readPrice: () => 1, sleep: () => {}, now: () => 0, lag, unexpectedErrors: errors });
        expectEqual('a PUT that was not 200 waits for nothing', [refused, lag.samples.length, errors.added.length], [null, 1, 0]);
    },
    'flow-wires-the-metric': () => {
        const calls = [];
        const lag = fakeLag();
        const errors = fakeErrors();
        let clock = 0;
        const deps = {
            send: (method, template, options) => {
                calls.push([method, template, options.params.product, options.body ? options.body.price_cents : null]);
                clock += 10;

                return { status: 200, price: method === 'PUT' ? null : 10001 };
            },
            json: (response) => response.price,
            sleep: (seconds) => {
                clock += seconds * 1000;
            },
            now: () => clock,
            lag,
            unexpectedErrors: errors,
        };
        const team = { id: 'team-1', body: { name: 'Produto da Equipe', description: 'd', image_url: null, status: 'active', category_ids: ['c1'] } };

        const price = teamPriceStep(deps, team, 10000);

        expectEqual('the price it wrote', price, 10001);
        expectEqual('a PUT to the admin product, then a read of the storefront product', calls, [
            ['PUT', '/api/admin/products/{product}', 'team-1', 10001],
            ['GET', '/api/products/{product}', 'team-1', null],
        ]);
        expectEqual('the lag goes to the metric: 250 ms of sleep and 10 ms of read', lag.samples, [260]);
        expectEqual('the metric of the flow is storefront_projection_lag', PROJECTION_LAG_METRIC, 'storefront_projection_lag');
        expectEqual('and it is the Trend the flow adds to', typeof storefrontProjectionLag.add, 'function');
    },
    'gives-up-after-60s': () => {
        const lag = fakeLag();
        const errors = fakeErrors();
        let reads = 0;
        const sleeps = [];

        measureProjectionLag({ price: 10001, put: () => true, readPrice: () => (reads++, 10000), sleep: (s) => sleeps.push(s), now: () => 0, lag, unexpectedErrors: errors });

        expectEqual('240 reads in 60 s', reads, 240);
        expectEqual('waited exactly 60 s', sleeps.reduce((sum, s) => sum + s, 0), 60);
        expectEqual('exactly one unexpected error', errors.added, [true]);
        expectEqual('and no sample', lag.samples, []);

        const seen = fakeErrors();
        measureProjectionLag({ price: 1, put: () => true, readPrice: () => 1, sleep: () => {}, now: () => 0, lag: fakeLag(), unexpectedErrors: seen });
        expectEqual('a wait that sees the price counts no error', seen.added, []);
    },
};

export default function () {
    runCase(cases);
}
