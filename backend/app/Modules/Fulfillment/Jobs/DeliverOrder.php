<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Jobs;

use App\Modules\Fulfillment\UseCases\DeliverOrderUseCase;
use App\Modules\Ordering\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fulfillment: simulates the carrier delivering a paid order (no real carrier integration).
 */
class DeliverOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        app(DeliverOrderUseCase::class)->execute($this->order);
    }
}
