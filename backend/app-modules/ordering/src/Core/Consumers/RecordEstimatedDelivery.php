<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\Consumers;

use Modules\Fulfillment\Events\DeliveryScheduled;
use Modules\Messaging\Contracts\EventConsumer;
use Modules\Ordering\Core\UseCases\RecordEstimatedDeliveryUseCase;

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
