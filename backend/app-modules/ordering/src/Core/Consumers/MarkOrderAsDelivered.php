<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\Consumers;

use Modules\Fulfillment\Events\OrderDelivered;
use Modules\Messaging\Contracts\EventConsumer;
use Modules\Ordering\Core\UseCases\MarkOrderAsDeliveredUseCase;

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
