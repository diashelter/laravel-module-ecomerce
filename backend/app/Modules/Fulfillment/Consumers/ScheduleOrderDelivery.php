<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Consumers;

use App\Modules\Fulfillment\UseCases\ScheduleDeliveryUseCase;
use App\Modules\Ordering\Events\OrderPaid;
use App\Modules\Shared\Messaging\EventConsumer;

/**
 * Consumer (fulfillment): order paid -> delivery scheduled.
 */
class ScheduleOrderDelivery implements EventConsumer
{
    public function handle(OrderPaid $event): void
    {
        app(ScheduleDeliveryUseCase::class)->execute($event->orderId, $event->deliveryBusinessDays);
    }
}
