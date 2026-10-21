<?php

declare(strict_types=1);

namespace Modules\Payment\Core\ValueObjects;

/**
 * An order as the payment module sees it (see OrdersToPay): who placed it, what it costs and whether it
 * is awaiting payment. The amount charged always comes from here, never from the request.
 */
final readonly class OrderToPay
{
    public function __construct(
        public string $id,
        public string $customerId,
        public int $totalCents,
        public bool $awaitingPayment,
    ) {}
}
