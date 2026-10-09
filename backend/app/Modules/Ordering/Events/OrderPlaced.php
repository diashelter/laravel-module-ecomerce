<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an order has been created. ShouldDispatchAfterCommit guarantees the
 * event is only dispatched once the checkout transaction is committed.
 */
class OrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $orderId) {}
}
