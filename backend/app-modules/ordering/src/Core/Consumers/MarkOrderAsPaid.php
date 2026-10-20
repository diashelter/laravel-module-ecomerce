<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\Consumers;

use Modules\Messaging\Contracts\EventConsumer;
use Modules\Ordering\Core\UseCases\MarkOrderAsPaidUseCase;
use Modules\Payment\Events\PaymentApproved;

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
