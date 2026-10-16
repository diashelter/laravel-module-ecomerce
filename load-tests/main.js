// Entry point of every profile: `make load-test TYPE=<profile>` runs it with PROFILE set (load-tests/README.md).
import { PROFILE } from './lib/http.js';
import { buildOptions } from './lib/profiles.js';
import { discover } from './lib/dataset.js';
import { summarize } from './lib/summary.js';
import { storefront as storefrontFlow } from './flows/storefront.js';
import { checkout as checkoutFlow } from './flows/checkout.js';
import { backoffice as backofficeFlow } from './flows/backoffice.js';
import { BURST_DELAY_MS, contention as contentionScenario, contentionTeardown, crossOrders as crossOrdersScenario } from './scenarios/contention.js';
import { limitsBurst as limitsBurstScenario, limitsLogin as limitsLoginScenario, limitsSequential as limitsSequentialScenario } from './scenarios/limits.js';

export const options = buildOptions(PROFILE);

export function setup() {
    const data = discover();

    // When the scenarios start: the contention burst waits from here, and the breakpoint ramp counts from here.
    return { ...data, startedAt: Date.now(), burstAt: Date.now() + BURST_DELAY_MS };
}

export function storefront(data) {
    storefrontFlow(data);
}

export function checkout(data) {
    checkoutFlow(data);
}

export function backoffice(data) {
    backofficeFlow(data);
}

export function contention(data) {
    contentionScenario(data);
}

export function crossOrders(data) {
    crossOrdersScenario(data);
}

export function limitsSequential(data) {
    limitsSequentialScenario(data);
}

export function limitsLogin() {
    limitsLoginScenario();
}

export function limitsBurst() {
    limitsBurstScenario();
}

export function teardown(data) {
    if (PROFILE === 'contention') {
        contentionTeardown(data);
    }
}

export function handleSummary(data) {
    return summarize(data);
}
