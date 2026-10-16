<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\UseCases;

use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Shared\Messaging\EventOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * System: simulates the carrier delivering the order (no real carrier integration) and
 * announces it. Fulfillment never changes the order status itself (see MarkOrderAsDeliveredUseCase).
 */
final class DeliverOrderUseCase
{
    public function __construct(private readonly EventOutbox $outbox) {}

    public function execute(string $orderId): void
    {
        Log::info('Carrier delivered the order.', ['order_id' => $orderId]);

        // A transaction only for the message. A retried job announces the delivery again: the
        // ordering side ignores duplicates.
        DB::transaction(fn () => $this->outbox->record(new OrderDelivered($orderId)));
    }
}
