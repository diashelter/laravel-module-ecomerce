// Proves the lines computed after a run (checks C90 and C91), the burst overshoot and the breakpoint rate at stop,
// and what the burst counter counts (check C63).
import { expectEqual, runCase } from './harness.js';
import { derivedLines } from '../lib/summary.js';
import { passedTheLimiter } from '../scenarios/limits.js';

export { options } from './harness.js';

const NOW = 1_000_000_000;

function burst(passed) {
    return { metrics: { rate_limit_burst_passed: { values: { count: passed } } } };
}

const cases = {
    overshoot: () => {
        expectEqual('11 passed: 1 beyond the limit of 10', derivedLines(burst(11), 'limits', NOW), ['metric\trate_limit_overshoot\t1\t1']);
        expectEqual('10 passed: none beyond', derivedLines(burst(10), 'limits', NOW), ['metric\trate_limit_overshoot\t1\t0']);
        expectEqual('8 passed: never negative', derivedLines(burst(8), 'limits', NOW), ['metric\trate_limit_overshoot\t1\t0']);
        expectEqual('no burst, no line', derivedLines({ metrics: {} }, 'limits', NOW), []);
    },
    'burst-passed': () => {
        expectEqual('a wrong password passed the limiter', passedTheLimiter(422), true);
        expectEqual('a 429 did not', passedTheLimiter(429), false);
        expectEqual('a 200 passed it', passedTheLimiter(200), true);
    },
    'breakpoint-rate': () => {
        const data = { metrics: {}, setup_data: { startedAt: NOW - 450_000 } };

        expectEqual('the rate after 450 s of ramp', derivedLines(data, 'breakpoint', NOW), ['metric\tbreakpoint_rate_at_stop\t1\t252.5']);
        expectEqual('only for the breakpoint', derivedLines(data, 'load', NOW), []);
    },
};

export default function () {
    runCase(cases);
}
