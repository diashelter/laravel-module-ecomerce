<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\InboundEventOutsideConsumers;

use Modules\Ordering\Core\InboundEvents\PaymentApproved;

/** Not a violation: a consumer reads the inbound event. Never loaded. */
final class Allowed
{
    public function make(): string
    {
        return PaymentApproved::class;
    }
}
