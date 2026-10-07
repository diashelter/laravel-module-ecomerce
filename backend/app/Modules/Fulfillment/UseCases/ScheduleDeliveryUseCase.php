<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\UseCases;

use App\Modules\Fulfillment\Events\DeliveryScheduled;
use App\Modules\Fulfillment\Jobs\DeliverOrder;
use App\Modules\Fulfillment\Services\DeliveryCalendar;
use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * System (reacting to OrderPaid): works out the estimated delivery day from the business days
 * quoted when the order was placed, announces it, and hands the order to the (fake) carrier.
 */
final class ScheduleDeliveryUseCase
{
    public function __construct(private readonly DeliveryCalendar $calendar) {}

    public function execute(Order $order): void
    {
        $estimatedOn = $this->calendar->estimate(now(), $order->delivery_business_days);

        // Only ids and dates: the delivery address never goes to the log.
        Log::info('Delivery scheduled.', [
            'order_id' => $order->id,
            'estimated_delivery_on' => $estimatedOn->format('Y-m-d'),
        ]);

        // A retried job announces the date again: the ordering side keeps the first one.
        DeliveryScheduled::dispatch($order, $estimatedOn);

        DeliverOrder::dispatch($order)
            ->delay(now()->addSeconds(config('shop.delivery_delay_seconds')));
    }
}
