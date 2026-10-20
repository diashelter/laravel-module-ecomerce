<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\InfrastructureUsesBusiness;

use Modules\Ordering\Contracts\PayableOrders;

/** Deliberate violation: an infrastructure module names a business module. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return PayableOrders::class;
    }
}
