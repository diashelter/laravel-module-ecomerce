<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\ModuleTestUsesBusiness;

use Modules\Ordering\Persistence\Models\Order;

/** Deliberate violation: a test of the payment module names the ordering module. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return Order::class;
    }
}
