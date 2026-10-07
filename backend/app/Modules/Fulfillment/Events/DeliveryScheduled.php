<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Events;

use App\Modules\Ordering\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fulfillment: the delivery of a paid order was scheduled for an estimated day. Ordering reacts by
 * recording the date on the order. Fulfillment never writes to the order itself.
 */
class DeliveryScheduled implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order, public CarbonImmutable $estimatedDeliveryOn) {}
}
