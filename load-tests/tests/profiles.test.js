// Proves the shape and the thresholds of every profile (checks C63, C65 to C73 and C77).
import { expectEqual, expectTrue, runCase } from './harness.js';
import { BASELINE, DEFAULT_LATENCY, buildOptions, latencyThresholds } from '../lib/profiles.js';
import { splitVus } from '../lib/mix.js';

export { options } from './harness.js';

const README = open('../README.md');

/** The stages of the three flows added up, as one list of [duration, total VUs]. */
function totalStages(options) {
    const flows = Object.values(options.scenarios);

    return flows[0].stages.map((stage, index) => [stage.duration, flows.reduce((sum, flow) => sum + flow.stages[index].target, 0)]);
}

function totalStart(options) {
    return Object.values(options.scenarios).reduce((sum, flow) => sum + flow.startVUs, 0);
}

function expectVolumeThresholds(profile) {
    expectEqual(`${profile} thresholds`, buildOptions(profile, null).thresholds, {
        unexpected_errors: ['rate<0.01'],
        'http_req_duration{flow:storefront}': ['p(95)<500'],
        'http_req_duration{name:POST /api/orders}': ['p(95)<1000'],
        order_payable_wait: ['p(95)<10000'],
    });
}

/** The p95 columns of the first `load` row of the baseline table, or null while there is none. */
function baselineFromReadme() {
    const header = README.split('\n').find((line) => line.startsWith('| Data |'));
    const row = README.split('\n').find((line) => /^\| \d{4}-\d{2}-\d{2} \|/.test(line) && line.split('|')[4].trim() === 'load');

    if (!header || !row) {
        return null;
    }

    const columns = header.split('|').map((c) => c.trim());
    const cells = row.split('|').map((c) => c.trim());
    const value = (name) => Number(cells[columns.indexOf(name)].replace(/[^\d.]/g, ''));

    return {
        storefrontP95: value('p95 vitrine (ms)'),
        ordersP95: value('p95 POST /api/orders (ms)'),
        payableWaitP95: value('p95 order_payable_wait (ms)'),
    };
}

const cases = {
    smoke: () => {
        const options = buildOptions('smoke', null);

        for (const flow of ['storefront', 'checkout', 'backoffice']) {
            const s = options.scenarios[flow];
            expectEqual(`${flow}: 1 VU, 1 iteration`, [s.executor, s.vus, s.iterations, s.exec], ['per-vu-iterations', 1, 1, flow]);
        }

        expectEqual('smoke thresholds, no latency', options.thresholds, { checks: ['rate==1'], unexpected_errors: ['rate==0'] });
    },
    load: () => {
        const options = buildOptions('load', null);
        expectEqual('starts at 0', totalStart(options), 0);
        expectEqual('load stages', totalStages(options), [['1m', 50], ['5m', 50], ['1m', 0]]);
    },
    stress: () => {
        const options = buildOptions('stress', null);
        expectEqual('starts at 0', totalStart(options), 0);
        expectEqual('stress stages', totalStages(options), [['2m', 150], ['5m', 150], ['2m', 0]]);
    },
    spike: () => {
        const options = buildOptions('spike', null);
        expectEqual('starts at 50', totalStart(options), 50);
        expectEqual('spike stages', totalStages(options), [['1m', 50], ['10s', 300], ['1m', 300], ['10s', 50], ['2m', 50]]);
    },
    breakpoint: () => {
        const options = buildOptions('breakpoint', null);
        const flows = Object.values(options.scenarios);

        expectEqual('ramping-arrival-rate', flows.map((s) => s.executor), ['ramping-arrival-rate', 'ramping-arrival-rate', 'ramping-arrival-rate']);
        expectEqual('from 5 iterations/s', flows.reduce((sum, s) => sum + s.startRate, 0), 5);
        expectEqual('to 500 iterations/s in 15 min', totalStages({ scenarios: options.scenarios }), [['15m', 500]]);
        expectEqual('per second', flows.map((s) => s.timeUnit), ['1s', '1s', '1s']);
        expectEqual('abort thresholds', options.thresholds, {
            unexpected_errors: [{ threshold: 'rate<0.05', abortOnFail: true, delayAbortEval: '30s' }],
            http_req_duration: [{ threshold: 'p(95)<2000', abortOnFail: true, delayAbortEval: '30s' }],
        });
    },
    soak: () => {
        const options = buildOptions('soak', null);
        expectTrue('holds 50 VUs for 1 hour', totalStages(options).some(([duration, target]) => duration === '1h' && target === 50));
    },
    split: () => {
        expectEqual('50 VUs', splitVus(50), { storefront: 38, checkout: 10, backoffice: 2 });
        expectEqual('150 VUs', splitVus(150), { storefront: 113, checkout: 30, backoffice: 7 });
        expectEqual('300 VUs', splitVus(300), { storefront: 225, checkout: 60, backoffice: 15 });
        expectEqual('500 iterations/s', splitVus(500), { storefront: 375, checkout: 100, backoffice: 25 });
        expectEqual('0 stays 0', splitVus(0), { storefront: 0, checkout: 0, backoffice: 0 });
        expectEqual('5 keeps a VU per flow', splitVus(5), { storefront: 3, checkout: 1, backoffice: 1 });
    },
    'volume-thresholds': () => {
        expectEqual('defaults before the baseline', latencyThresholds(null), DEFAULT_LATENCY);
        expectVolumeThresholds('load');
        expectVolumeThresholds('stress');
        expectVolumeThresholds('soak');
    },
    'spike-thresholds': () => {
        expectEqual('spike thresholds', buildOptions('spike', null).thresholds, { unexpected_errors: ['rate<0.05'] });
    },
    'thresholds-from-baseline': () => {
        const recorded = baselineFromReadme();

        expectTrue('the README has a load row', recorded !== null);
        expectEqual('BASELINE is the load row of the README', BASELINE, recorded);

        const up = (ms) => Math.ceil((ms * 1.2) / 10) * 10;
        expectEqual('load thresholds are the recorded p95 times 1.2', buildOptions('load').thresholds, {
            unexpected_errors: ['rate<0.01'],
            'http_req_duration{flow:storefront}': [`p(95)<${up(recorded.storefrontP95)}`],
            'http_req_duration{name:POST /api/orders}': [`p(95)<${up(recorded.ordersP95)}`],
            order_payable_wait: [`p(95)<${up(recorded.payableWaitP95)}`],
        });
    },
    'limits-overshoot-unbounded': () => {
        const thresholds = buildOptions('limits', null).thresholds;

        expectEqual('no threshold on the burst', Object.keys(thresholds).filter((name) => name.startsWith('rate_limit')), []);
        expectEqual('only the checks and the unexpected errors', thresholds, { checks: ['rate==1'], unexpected_errors: ['rate==0'] });
    },
};

export default function () {
    runCase(cases);
}
