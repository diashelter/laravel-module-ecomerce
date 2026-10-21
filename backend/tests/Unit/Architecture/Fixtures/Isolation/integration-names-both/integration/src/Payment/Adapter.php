<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Isolation\IntegrationNamesBoth;

use Modules\Ordering\Contracts\PayableOrders;
use Modules\Payment\Contracts\OrdersToPay;

/** Not a violation: the integration package names both modules. Never loaded. */
final class Adapter
{
    /** @return list<string> */
    public function make(): array
    {
        return [PayableOrders::class, OrdersToPay::class];
    }
}
