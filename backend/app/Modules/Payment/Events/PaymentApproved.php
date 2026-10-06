<?php

declare(strict_types=1);

namespace App\Modules\Payment\Events;

use App\Modules\Ordering\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment: the (fake) payment of an order was approved. Ordering reacts by marking the order
 * as paid; payment itself never changes the order status.
 */
class PaymentApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
