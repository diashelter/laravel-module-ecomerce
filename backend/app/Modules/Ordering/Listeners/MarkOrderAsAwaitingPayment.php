<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Listeners;

use App\Modules\Ordering\Events\OrderPlaced;
use App\Modules\Ordering\UseCases\MarkOrderAsAwaitingPaymentUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued listener: runs in the queue-worker container, not in the HTTP request.
 */
class MarkOrderAsAwaitingPayment implements ShouldQueue
{
    public int $tries = 3;

    public function handle(OrderPlaced $event): void
    {
        app(MarkOrderAsAwaitingPaymentUseCase::class)->execute($event->order);
    }
}
