<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Listeners;

use App\Modules\Fulfillment\Events\DeliveryScheduled;
use App\Modules\Ordering\UseCases\RecordEstimatedDeliveryUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued listener (ordering): delivery scheduled -> estimated delivery day recorded on the order.
 */
class RecordEstimatedDelivery implements ShouldQueue
{
    public int $tries = 3;

    public function handle(DeliveryScheduled $event): void
    {
        app(RecordEstimatedDeliveryUseCase::class)->execute($event->orderId, $event->estimatedDeliveryOn);
    }
}
