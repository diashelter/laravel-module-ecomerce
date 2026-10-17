// The p95 of the accepted `load` row of load-tests/README.md, in ms (tests/profiles.test.js compares the two).
// Kept apart from the code a run measures: recording a row changes only this file and the README.
// null would bring back DEFAULT_LATENCY of lib/profiles.js.
export const BASELINE = { storefrontP95: 30, ordersP95: 55.4, payableWaitP95: 2035 };
