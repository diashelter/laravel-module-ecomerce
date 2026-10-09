<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ordering: the order was marked as paid. Fired only once, by the transition that actually
 * moved the order to "payment_approved", so fulfillment never starts for an unpaid order. It carries
 * the business days quoted at checkout, so fulfillment schedules the delivery without reading the order.
 */
class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $orderId, public int $deliveryBusinessDays) {}
}
