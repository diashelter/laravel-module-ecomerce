<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Isolation\OrderingNamesPayment;

use Modules\Payment\Events\PaymentApproved;

/** Deliberate violation: the ordering module names the isolated payment module. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return PaymentApproved::class;
    }
}
