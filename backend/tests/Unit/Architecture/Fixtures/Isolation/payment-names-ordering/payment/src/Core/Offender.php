<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Isolation\PaymentNamesOrdering;

use Modules\Ordering\Contracts\PayableOrders;

/** Deliberate violation: the isolated payment module names the ordering module. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return PayableOrders::class;
    }
}
