<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Events;

use App\Modules\Ordering\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ordering: the order was marked as paid. Fired only once, by the transition that actually
 * moved the order to "payment_approved", so fulfillment never starts for an unpaid order.
 */
class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
