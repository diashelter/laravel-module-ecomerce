<?php

/*
 * Deliberate violation: a test of the payment module calls a helper of the composition suite. No namespace,
 * like a real module test, so the call is the only thing the rule can report. Never loaded.
 */
it('pays for an order of a product in stock', function () {
    productWithStock(1);
});
