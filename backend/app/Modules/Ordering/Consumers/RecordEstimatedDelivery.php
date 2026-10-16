<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Consumers;

use App\Modules\Fulfillment\Events\DeliveryScheduled;
use App\Modules\Ordering\UseCases\RecordEstimatedDeliveryUseCase;
use App\Modules\Shared\Messaging\EventConsumer;

/**
 * Consumer (ordering): delivery scheduled -> estimated delivery day recorded on the order.
 */
class RecordEstimatedDelivery implements EventConsumer
{
    public function handle(DeliveryScheduled $event): void
    {
        app(RecordEstimatedDeliveryUseCase::class)->execute($event->orderId, $event->estimatedDeliveryOn);
    }
}
