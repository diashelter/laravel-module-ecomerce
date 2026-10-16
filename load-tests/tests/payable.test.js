// Proves the wait until an order can be paid (checks C41, C42 and C43), with a fake clock.
import { expectEqual, runCase } from './harness.js';
import { payWhenPayable, waitUntilPayable } from '../lib/payable.js';

export { options } from './harness.js';

function fakeOrder(statuses) {
    const sleeps = [];
    let reads = 0;
    const readStatus = () => statuses[Math.min(reads++, statuses.length - 1)];
    const sleep = (seconds) => sleeps.push(seconds);

    return { readStatus, sleep, sleeps, reads: () => reads };
}

const cases = {
    'polls-every-2s': () => {
        const order = fakeOrder(['placed', 'placed', 'awaiting_payment']);
        const wait = waitUntilPayable(order.readStatus, order.sleep);

        expectEqual('sleeps 2 s before every read, the first included', order.sleeps, [2, 2, 2]);
        expectEqual('reads until awaiting_payment', order.reads(), 3);
        expectEqual('payable after 6 s', wait, { payable: true, waitedSeconds: 6 });
    },
    'gives-up-after-60s': () => {
        const order = fakeOrder(['placed']);
        const wait = waitUntilPayable(order.readStatus, order.sleep);

        expectEqual('30 reads in 60 s', order.reads(), 30);
        expectEqual('waited exactly 60 s', order.sleeps.reduce((sum, s) => sum + s, 0), 60);
        expectEqual('not payable', wait, { payable: false, waitedSeconds: 60 });
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
