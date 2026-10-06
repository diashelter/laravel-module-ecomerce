<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Listeners;

use App\Modules\Ordering\UseCases\MarkOrderAsPaidUseCase;
use App\Modules\Payment\Events\PaymentApproved;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued listener (ordering): payment approved -> order paid.
 */
class MarkOrderAsPaid implements ShouldQueue
{
    public int $tries = 3;

    public function handle(PaymentApproved $event): void
    {
        app(MarkOrderAsPaidUseCase::class)->execute($event->order);
    }
}
