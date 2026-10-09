<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Listeners;

use App\Modules\Fulfillment\Events\OrderDelivered;
use App\Modules\Ordering\UseCases\MarkOrderAsDeliveredUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued listener (ordering): order delivered by the carrier -> order closed.
 */
class MarkOrderAsDelivered implements ShouldQueue
{
    public int $tries = 3;

    public function handle(OrderDelivered $event): void
    {
        app(MarkOrderAsDeliveredUseCase::class)->execute($event->orderId);
    }
}
