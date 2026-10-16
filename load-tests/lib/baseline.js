// The p95 of the accepted `load` row of load-tests/README.md, in ms (tests/profiles.test.js compares the two).
// Kept apart from the code a run measures: recording a row changes only this file and the README.
// null would bring back DEFAULT_LATENCY of lib/profiles.js.
export const BASELINE = { storefrontP95: 34, ordersP95: 51.8, payableWaitP95: 2045 };
