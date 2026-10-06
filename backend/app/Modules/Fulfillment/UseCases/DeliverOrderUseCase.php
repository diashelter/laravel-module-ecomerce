<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\UseCases;

use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * System: simulates the carrier delivering the order (no real carrier integration) and
 * announces it. Fulfillment never changes the order status itself (see MarkOrderAsDeliveredUseCase).
 */
final class DeliverOrderUseCase
{
    public function execute(Order $order): void
    {
        Log::info('Carrier delivered the order.', ['order_id' => $order->id]);

        // A retried job announces the delivery again: the ordering side ignores duplicates.
        OrderDelivered::dispatch($order);
    }
}
