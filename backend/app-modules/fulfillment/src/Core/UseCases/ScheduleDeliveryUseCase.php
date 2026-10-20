<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Core\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Fulfillment\Core\Jobs\DeliverOrder;
use Modules\Fulfillment\Core\Services\DeliveryCalendar;
use Modules\Fulfillment\Events\DeliveryScheduled;
use Modules\Fulfillment\Persistence\Repositories\DeliveryRepository;
use Modules\Messaging\Contracts\EventOutbox;

/**
 * System (reacting to OrderPaid): works out the estimated delivery day from the business days
 * quoted when the order was placed (carried by the event), announces it, and hands the order to
 * the (fake) carrier.
 *
 * OrderPaid is delivered at least once. The delivery row of the order (unique order_id) decides:
 * only the delivery that creates it announces the date, in the same transaction. Every delivery
 * of a not yet delivered order queues the DeliverOrder, which repairs a queueing that failed after
 * the commit and is harmless to repeat.
 */
final class ScheduleDeliveryUseCase
{
    public function __construct(
        private readonly DeliveryCalendar $calendar,
        private readonly DeliveryRepository $deliveries,
        private readonly EventOutbox $outbox,
    ) {}

    public function execute(string $orderId, int $deliveryBusinessDays): void
    {
        $estimatedOn = $this->calendar->estimate(now(), $deliveryBusinessDays);

        $scheduled = DB::transaction(function () use ($orderId, $estimatedOn): bool {
            if (! $this->deliveries->schedule($orderId, $estimatedOn)) {
                return false;
            }

            $this->outbox->record(new DeliveryScheduled($orderId, $estimatedOn));

            return true;
        });

        if ($scheduled) {
            // Only ids and dates: the delivery address never goes to the log.
            Log::info('Delivery scheduled.', [
                'order_id' => $orderId,
                'estimated_delivery_on' => $estimatedOn->format('Y-m-d'),
            ]);
        } elseif ($this->deliveries->isDelivered($orderId)) {
            return;
        }

        // Queued after the commit: when recording fails, no delivery is queued and the retry does both.
        DeliverOrder::dispatch($orderId)
            ->delay(now()->addSeconds(config('shop.delivery_delay_seconds')));
    }
}
