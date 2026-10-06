<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\UseCases;

use App\Modules\Fulfillment\Jobs\DeliverOrder;
use App\Modules\Ordering\Models\Order;

/**
 * System (reacting to OrderPaid): hands a paid order to the (fake) carrier.
 */
final class ScheduleDeliveryUseCase
{
    public function execute(Order $order): void
    {
        DeliverOrder::dispatch($order)
            ->delay(now()->addSeconds(config('shop.delivery_delay_seconds')));
    }
}
