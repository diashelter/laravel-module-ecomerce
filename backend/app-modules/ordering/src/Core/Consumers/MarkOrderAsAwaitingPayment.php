<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\Consumers;

use Modules\Messaging\Contracts\EventConsumer;
use Modules\Ordering\Core\UseCases\MarkOrderAsAwaitingPaymentUseCase;
use Modules\Ordering\Events\OrderPlaced;

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
