<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\InfrastructureUsesBusiness;

/** Deliberate violation: an infrastructure module names a business module. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return \Modules\Ordering\Contracts\PayableOrders::class;
    }
}
