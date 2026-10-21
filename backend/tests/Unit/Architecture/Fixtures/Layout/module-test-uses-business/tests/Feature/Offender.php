<?php

use Modules\Ordering\Persistence\Models\Order;

/*
 * Deliberate violation: a test of the payment module names the ordering module. No namespace, like a real
 * module test, so the name is the only thing the rule can report. Never loaded.
 */
it('pays for a real order', function () {
    Order::factory()->create();
});
