<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Consumers;

use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Ordering\UseCases\MarkOrderAsDeliveredUseCase;
use App\Modules\Shared\Messaging\EventConsumer;

/**
 * Consumer (ordering): order delivered by the carrier -> order closed.
 */
class MarkOrderAsDelivered implements EventConsumer
{
    public function handle(OrderDelivered $event): void
    {
        app(MarkOrderAsDeliveredUseCase::class)->execute($event->orderId);
    }
}
