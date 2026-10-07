<?php

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

/**
 * What the customer sends to pay: only the card. The amount always comes from the order.
 */
final readonly class PayOrderDTO
{
    public function __construct(public string $cardToken) {}
}
