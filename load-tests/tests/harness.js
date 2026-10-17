// A tiny test runner on top of k6 itself, so the decisions of the scripts are proven inside Docker with nothing
// else installed: `k6 run -e CASE=<case> tests/<file>.test.js`. One case per run; it fails through the
// `checks: rate==1` threshold (exit code 99), and an unknown case or an exception fails it too.
import { check } from 'k6';

export const options = {
    vus: 1,
    iterations: 1,
    thresholds: { checks: ['rate==1'] },
};

export function expectEqual(name, actual, expected) {
    const ok = JSON.stringify(actual) === JSON.stringify(expected);

    if (!ok) {
        console.error(`${name}: expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
    }

    check(null, { [name]: () => ok });
}

export function expectTrue(name, value) {
    expectEqual(name, value, true);
}

export function runCase(cases) {
    const name = __ENV.CASE;
    const testCase = cases[name];

    if (!testCase) {
        check(null, { [`unknown case "${name}"; cases: ${Object.keys(cases).join(', ')}`]: () => false });
        return;
    }

    try {
        testCase();
    } catch (error) {
        console.error(`${name}: ${error}`);
        check(null, { [`${name} threw`]: () => false });
    }
}
