<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Core\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Fulfillment\Events\OrderDelivered;
use Modules\Fulfillment\Persistence\Repositories\DeliveryRepository;
use Modules\Messaging\Contracts\EventOutbox;

/**
 * System: simulates the carrier delivering the order (no real carrier integration) and
 * announces it. Fulfillment never changes the order status itself (see MarkOrderAsDeliveredUseCase).
 *
 * The job is retried and may be queued more than once, so only the run that fills delivered_at
 * announces the delivery, in the same transaction; any other run changes nothing. So does a job for
 * an order without a delivery row (queued before the deliveries table existed, which only happens in
 * a development database): writing the row there is left until a real backfill needs it.
 */
final class DeliverOrderUseCase
{
    public function __construct(
        private readonly DeliveryRepository $deliveries,
        private readonly EventOutbox $outbox,
    ) {}

    public function execute(string $orderId): void
    {
        $delivered = DB::transaction(function () use ($orderId): bool {
            if (! $this->deliveries->markDelivered($orderId, now())) {
                return false;
            }

            $this->outbox->record(new OrderDelivered($orderId));

            return true;
        });

        if ($delivered) {
            Log::info('Carrier delivered the order.', ['order_id' => $orderId]);
        }
    }
}
