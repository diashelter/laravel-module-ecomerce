// Pure: the k6 options of each profile (plan S7). Shapes, thresholds and the VU split are decisions, proven
// one row at a time by tests/profiles.test.js.
import { splitVus } from './mix.js';

export const PROFILES = ['smoke', 'load', 'stress', 'spike', 'breakpoint', 'soak', 'contention', 'limits'];
export const FLOWS = ['storefront', 'checkout', 'backoffice'];

// Until the first `load` row of the baseline (load-tests/README.md) these are the latency thresholds.
export const DEFAULT_LATENCY = { storefrontP95: 500, ordersP95: 1000, payableWaitP95: 10000 };

// The p95 of the accepted `load` row, in ms, or null before it exists (tests/profiles.test.js compares it with the README).
export const BASELINE = null;

const SHAPES = {
    load: { startVUs: 0, stages: [['1m', 50], ['5m', 50], ['1m', 0]] },
    stress: { startVUs: 0, stages: [['2m', 150], ['5m', 150], ['2m', 0]] },
    spike: { startVUs: 50, stages: [['1m', 50], ['10s', 300], ['1m', 300], ['10s', 50], ['2m', 50]] },
    soak: { startVUs: 0, stages: [['1m', 50], ['1h', 50], ['1m', 0]] },
};

export const BREAKPOINT = { startRate: 5, targetRate: 500, duration: '15m', preAllocatedVUs: { storefront: 50, checkout: 15, backoffice: 5 }, maxVUs: { storefront: 750, checkout: 200, backoffice: 50 } };

const COMMON = {
    // A VU logs in once and keeps its session across iterations, as a person keeps the tab open.
    noCookiesReset: true,
    summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)', 'count'],
    setupTimeout: '3m',
};

/** The arrival rate the breakpoint ramp had reached after `seconds` (iterations/s, all flows). */
export function breakpointRateAt(seconds) {
    const total = 15 * 60;
    const progress = Math.min(1, Math.max(0, seconds / total));

    return BREAKPOINT.startRate + (BREAKPOINT.targetRate - BREAKPOINT.startRate) * progress;
}

/** The accepted p95 times 1.2, rounded up to the next 10 ms; the defaults while there is no baseline. */
export function latencyThresholds(baseline) {
    if (!baseline) {
        return DEFAULT_LATENCY;
    }

    const margin = (p95) => Math.ceil((p95 * 1.2) / 10) * 10;

    return {
        storefrontP95: margin(baseline.storefrontP95),
        ordersP95: margin(baseline.ordersP95),
        payableWaitP95: margin(baseline.payableWaitP95),
    };
}

export function volumeThresholds(baseline) {
    const latency = latencyThresholds(baseline);

    return {
        unexpected_errors: ['rate<0.01'],
        'http_req_duration{flow:storefront}': [`p(95)<${latency.storefrontP95}`],
        'http_req_duration{name:POST /api/orders}': [`p(95)<${latency.ordersP95}`],
        order_payable_wait: [`p(95)<${latency.payableWaitP95}`],
    };
}

function rampingVus(shape) {
    const scenarios = {};

    for (const flow of FLOWS) {
        scenarios[flow] = {
            executor: 'ramping-vus',
            exec: flow,
            startVUs: splitVus(shape.startVUs)[flow],
            stages: shape.stages.map(([duration, target]) => ({ duration, target: splitVus(target)[flow] })),
            gracefulRampDown: '30s',
            tags: { flow },
        };
    }

    return scenarios;
}

function smokeScenarios() {
    const scenarios = {};

    for (const flow of FLOWS) {
        scenarios[flow] = { executor: 'per-vu-iterations', exec: flow, vus: 1, iterations: 1, maxDuration: '5m', tags: { flow } };
    }

    return scenarios;
}

function breakpointScenarios() {
    const scenarios = {};

    for (const flow of FLOWS) {
        scenarios[flow] = {
            executor: 'ramping-arrival-rate',
            exec: flow,
            startRate: splitVus(BREAKPOINT.startRate)[flow],
            timeUnit: '1s',
            preAllocatedVUs: BREAKPOINT.preAllocatedVUs[flow],
            maxVUs: BREAKPOINT.maxVUs[flow],
            stages: [{ duration: BREAKPOINT.duration, target: splitVus(BREAKPOINT.targetRate)[flow] }],
            tags: { flow },
        };
    }

    return scenarios;
}

function abortWhen(threshold) {
    return [{ threshold, abortOnFail: true, delayAbortEval: '30s' }];
}

export function buildOptions(profile, baseline = BASELINE) {
    switch (profile) {
        case 'smoke':
            return { ...COMMON, scenarios: smokeScenarios(), thresholds: { checks: ['rate==1'], unexpected_errors: ['rate==0'] } };
        case 'load':
        case 'stress':
        case 'soak':
            return { ...COMMON, scenarios: rampingVus(SHAPES[profile]), thresholds: volumeThresholds(baseline) };
        case 'spike':
            return { ...COMMON, scenarios: rampingVus(SHAPES.spike), thresholds: { unexpected_errors: ['rate<0.05'] } };
        case 'breakpoint':
            return {
                ...COMMON,
                scenarios: breakpointScenarios(),
                thresholds: { unexpected_errors: abortWhen('rate<0.05'), http_req_duration: abortWhen('p(95)<2000') },
            };
        case 'contention':
            return {
                ...COMMON,
                scenarios: {
                    contention: { executor: 'per-vu-iterations', exec: 'contention', vus: 50, iterations: 1, maxDuration: '2m' },
                    cross_orders: { executor: 'per-vu-iterations', exec: 'crossOrders', vus: 100, iterations: 1, maxDuration: '2m' },
                },
                thresholds: {
                    checks: ['rate==1'],
                    unexpected_errors: ['rate==0'],
                    contention_created: ['count==10'],
                    contention_refused: ['count==40'],
                    contention_server_errors: ['count==0'],
                    cross_orders_created: ['count==100'],
                    cross_orders_server_errors: ['count==0'],
                },
            };
        case 'limits':
            return {
                ...COMMON,
                scenarios: {
                    sequential: { executor: 'per-vu-iterations', exec: 'limitsSequential', vus: 1, iterations: 1, maxDuration: '2m' },
                    // Starts after the one-minute window of the logins in setup() and in `sequential`.
                    login: { executor: 'per-vu-iterations', exec: 'limitsLogin', vus: 1, iterations: 1, startTime: '70s', maxDuration: '1m' },
                    burst: { executor: 'per-vu-iterations', exec: 'limitsBurst', vus: 30, iterations: 1, startTime: '140s', maxDuration: '1m' },
                },
                // No threshold on the burst: how many pass beyond the limit is recorded, never a failure (plan AC 58).
                thresholds: { checks: ['rate==1'], unexpected_errors: ['rate==0'] },
            };
        default:
            throw new Error(`unknown profile "${profile}"; one of: ${PROFILES.join(', ')}`);
    }
}
