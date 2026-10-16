<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Consumers;

use App\Modules\Ordering\UseCases\MarkOrderAsPaidUseCase;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Shared\Messaging\EventConsumer;

/**
 * Consumer (ordering): payment approved -> order paid.
 */
class MarkOrderAsPaid implements EventConsumer
{
    public function handle(PaymentApproved $event): void
    {
        app(MarkOrderAsPaidUseCase::class)->execute($event->orderId);
    }
}
