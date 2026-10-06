<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Events;

use App\Modules\Ordering\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fulfillment: the (fake) carrier delivered the order. Ordering reacts by closing the order.
 */
class OrderDelivered implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
