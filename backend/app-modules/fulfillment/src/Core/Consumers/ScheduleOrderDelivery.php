<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Core\Consumers;

use Modules\Fulfillment\Core\UseCases\ScheduleDeliveryUseCase;
use Modules\Messaging\Contracts\EventConsumer;
use Modules\Ordering\Events\OrderPaid;

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
