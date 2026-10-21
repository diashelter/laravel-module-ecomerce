<?php

/*
 * Deliberate violation: a test of the payment module calls a helper of the identity module. No namespace,
 * like a real module test, so the call is the only thing the rule can report. Never loaded.
 */
it('pays for the order of a real customer', function () {
    customer();
});
