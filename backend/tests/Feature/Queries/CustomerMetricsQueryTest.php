<?php

use App\Modules\Identity\Contracts\CustomerMetrics;

it('counts only customer accounts for the dashboard', function () {
    customer();
    customer();
    admin();
    support();

    expect(app(CustomerMetrics::class)->totalCustomers())->toBe(2);
});
