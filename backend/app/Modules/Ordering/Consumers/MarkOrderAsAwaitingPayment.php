<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Consumers;

use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\UseCases\MarkOrderAsAwaitingPaymentUseCase;
use App\Modules\Shared\Messaging\EventConsumer;

/**
 * Consumer (ordering): order placed -> order awaiting payment. Each delivery runs as its own
 * DeliverEvent job in the queue-worker container, not in the HTTP request.
 */
class MarkOrderAsAwaitingPayment implements EventConsumer
{
    public function handle(OrderPlaced $event): void
    {
        app(MarkOrderAsAwaitingPaymentUseCase::class)->execute($event->orderId);
    }
}
