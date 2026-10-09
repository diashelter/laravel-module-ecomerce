<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fulfillment: the (fake) carrier delivered the order. Ordering reacts by closing the order.
 */
class OrderDelivered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $orderId) {}
}
