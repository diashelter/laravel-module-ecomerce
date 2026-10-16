// Proves the traffic mix decisions (checks C36, C37, C39, C44, C45, C51 and C53).
import { expectEqual, runCase } from './harness.js';
import {
    customerForVu,
    shouldAdjustStock,
    shouldOrderUnavailable,
    shouldRegister,
    staffForVu,
    startsWithDeclinedCard,
} from '../lib/mix.js';

export { options } from './harness.js';

/** How many of 1,000 consecutive iterations of a VU take the turn, in a volume profile. */
function turns(decide, vu = 7) {
    let count = 0;

    for (let iteration = 0; iteration < 1000; iteration++) {
        if (decide('volume', vu, iteration)) {
            count++;
        }
    }

    return count;
}

const cases = {
    'register-rate': () => {
        expectEqual('volume registers in 10 of 1000', turns(shouldRegister), 10);
        expectEqual('another VU also in 10 of 1000', turns(shouldRegister, 1), 10);
        expectEqual('smoke always registers', [0, 1, 2].map((i) => shouldRegister('smoke', 1, i)), [true, true, true]);
    },
    'customer-for-vu': () => {
        expectEqual('vu 1', customerForVu(1), 'load-customer-1@example.com');
        expectEqual('vu 999', customerForVu(999), 'load-customer-999@example.com');
        expectEqual('vu 1000 wraps to 1', customerForVu(1000), 'load-customer-1@example.com');
        const numbers = Array.from({ length: 3000 }, (_, i) => customerForVu(i + 1));
        expectEqual('never customer 0', numbers.includes('load-customer-0@example.com'), false);
    },
    'unavailable-order-rate': () => {
        expectEqual('volume orders a sold out product in 50 of 1000', turns(shouldOrderUnavailable), 50);
        expectEqual('smoke always does', shouldOrderUnavailable('smoke', 1, 0), true);
    },
    'card-split': () => {
        const declined = turns(startsWithDeclinedCard);
        expectEqual('declined first in 100 of 1000', declined, 100);
        expectEqual('approved directly in 900 of 1000', 1000 - declined, 900);
    },
    'staff-for-vu': () => {
        expectEqual('odd VUs are the admin', [1, 3, 5].map(staffForVu), ['admin@example.com', 'admin@example.com', 'admin@example.com']);
        expectEqual('even VUs are the support', [2, 4, 6].map(staffForVu), ['suporte@example.com', 'suporte@example.com', 'suporte@example.com']);
    },
    'stock-adjust-rate': () => {
        expectEqual('volume adjusts the stock in 20 of 1000', turns(shouldAdjustStock), 20);
    },
};

export default function () {
    runCase(cases);
}
