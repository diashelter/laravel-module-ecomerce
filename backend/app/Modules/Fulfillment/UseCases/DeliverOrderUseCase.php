<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\UseCases;

use App\Modules\Fulfillment\Events\OrderDelivered;
use Illuminate\Support\Facades\Log;

/**
 * System: simulates the carrier delivering the order (no real carrier integration) and
 * announces it. Fulfillment never changes the order status itself (see MarkOrderAsDeliveredUseCase).
 */
final class DeliverOrderUseCase
{
    public function execute(int $orderId): void
    {
        Log::info('Carrier delivered the order.', ['order_id' => $orderId]);

        // A retried job announces the delivery again: the ordering side ignores duplicates.
        OrderDelivered::dispatch($orderId);
    }
}
