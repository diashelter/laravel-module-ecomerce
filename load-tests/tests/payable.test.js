// Proves the wait until an order can be paid (checks C41, C42 and C43), with a fake clock.
import { expectEqual, runCase } from './harness.js';
import { countGiveUp, payWhenPayable, placeAndWaitUntilPayable, waitUntilPayable } from '../lib/payable.js';

export { options } from './harness.js';

function fakeOrder(statuses) {
    const sleeps = [];
    let reads = 0;
    const readStatus = () => statuses[Math.min(reads++, statuses.length - 1)];
    const sleep = (seconds) => sleeps.push(seconds);

    return { readStatus, sleep, sleeps, reads: () => reads };
}

const cases = {
    'polls-every-250ms': () => {
        const order = fakeOrder(['placed', 'placed', 'awaiting_payment']);
        const wait = waitUntilPayable(order.readStatus, order.sleep);

        expectEqual('sleeps 0.25 s before every read, the first included', order.sleeps, [0.25, 0.25, 0.25]);
        expectEqual('reads until awaiting_payment', order.reads(), 3);
        expectEqual('payable after 0.75 s', wait, { payable: true, waitedSeconds: 0.75 });
    },
    'times-from-the-201': () => {
        // A fake clock in ms: the POST takes 300, every read 40, every sleep its seconds.
        let clock = 0;
        const reads = [];
        const statuses = ['placed', 'awaiting_payment'];
        const now = () => clock;
        const sleep = (seconds) => {
            clock += seconds * 1000;
        };
        const readStatus = (id) => {
            reads.push(id);
            clock += 40;
            return statuses[reads.length - 1];
        };
        const place = () => {
            clock += 300;
            return 'order-1';
        };

        const order = placeAndWaitUntilPayable(place, readStatus, sleep, now);

        expectEqual('reads the created order', reads, ['order-1', 'order-1']);
        expectEqual('waits like waitUntilPayable', order.wait, { payable: true, waitedSeconds: 0.5 });
        expectEqual('from the 201 to the read of awaiting_payment: 2 sleeps and 2 reads, not the POST', order.waitedMs, 580);
        expectEqual('the order placed', order.id, 'order-1');

        const before = clock;
        expectEqual('an order not created is not waited for', placeAndWaitUntilPayable(() => null, readStatus, sleep, now), null);
        expectEqual('no read and no sleep', [reads.length, clock], [2, before]);
    },
    'gives-up-after-60s': () => {
        const order = fakeOrder(['placed']);
        const wait = waitUntilPayable(order.readStatus, order.sleep);

        expectEqual('240 reads in 60 s', order.reads(), 240);
        expectEqual('waited exactly 60 s', order.sleeps.reduce((sum, s) => sum + s, 0), 60);
        expectEqual('not payable', wait, { payable: false, waitedSeconds: 60 });

        const errors = [];
        const metric = { add: (failed) => errors.push(failed) };
        expectEqual('the give up is not payable', countGiveUp(wait, metric), false);
        expectEqual('counts one unexpected error', errors, [true]);
        expectEqual('a payable wait counts none', [countGiveUp({ payable: true }, metric), errors.length], [true, 1]);
    },
    'pays-only-when-awaiting': () => {
        let payments = 0;
        const pay = () => {
            payments++;
            return true;
        };

        expectEqual('no call when not payable', payWhenPayable({ payable: false }, pay), null);
        expectEqual('no payment sent', payments, 0);
        expectEqual('pays when payable', payWhenPayable({ payable: true }, pay), true);
        expectEqual('one payment sent', payments, 1);
    },
};

export default function () {
    runCase(cases);
}
