<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\InboundEventOutsideConsumers;

use Modules\Ordering\Core\InboundEvents\PaymentApproved;

/** Deliberate violation: a use case names an inbound event, which only the producer may record. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return PaymentApproved::class;
    }
}
