<?php

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

/**
 * What any gateway needs to charge an order: a reference, the amount in cents (BRL) and the card.
 * It carries no Eloquent model, so an adapter never depends on the ordering module.
 */
final readonly class ChargeRequest
{
    public function __construct(
        public int $orderId,
        public int $amountCents,
        public string $cardToken,
    ) {}
}
