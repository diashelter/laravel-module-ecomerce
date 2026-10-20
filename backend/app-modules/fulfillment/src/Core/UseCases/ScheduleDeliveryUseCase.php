<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Core\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Fulfillment\Core\Jobs\DeliverOrder;
use Modules\Fulfillment\Core\Services\DeliveryCalendar;
use Modules\Fulfillment\Events\DeliveryScheduled;
use Modules\Messaging\Contracts\EventOutbox;

/**
 * System (reacting to OrderPaid): works out the estimated delivery day from the business days
 * quoted when the order was placed (carried by the event), announces it, and hands the order to
 * the (fake) carrier.
 */
final class ScheduleDeliveryUseCase
{
    public function __construct(
        private readonly DeliveryCalendar $calendar,
        private readonly EventOutbox $outbox,
    ) {}

    public function execute(string $orderId, int $deliveryBusinessDays): void
    {
        $estimatedOn = $this->calendar->estimate(now(), $deliveryBusinessDays);

        // Only ids and dates: the delivery address never goes to the log.
        Log::info('Delivery scheduled.', [
            'order_id' => $orderId,
            'estimated_delivery_on' => $estimatedOn->format('Y-m-d'),
        ]);

        // A transaction only for the message. A retried delivery announces the date again: the
        // ordering side keeps the first one.
        DB::transaction(fn () => $this->outbox->record(new DeliveryScheduled($orderId, $estimatedOn)));

        // Queued after the commit: when recording fails, no delivery is queued and the retry does both.
        DeliverOrder::dispatch($orderId)
            ->delay(now()->addSeconds(config('shop.delivery_delay_seconds')));
    }
}
