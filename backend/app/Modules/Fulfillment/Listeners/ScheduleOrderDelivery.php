<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Listeners;

use App\Modules\Fulfillment\UseCases\ScheduleDeliveryUseCase;
use App\Modules\Ordering\Events\OrderPaid;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued listener (fulfillment): order paid -> delivery scheduled.
 */
class ScheduleOrderDelivery implements ShouldQueue
{
    public int $tries = 3;

    public function handle(OrderPaid $event): void
    {
        app(ScheduleDeliveryUseCase::class)->execute($event->orderId, $event->deliveryBusinessDays);
    }
}
