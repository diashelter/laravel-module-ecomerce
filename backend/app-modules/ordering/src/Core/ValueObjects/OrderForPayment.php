<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\ValueObjects;

use Modules\Ordering\Core\Enums\OrderStatus;

/**
 * An order as the payment module sees it (see PayableOrders): who placed it, what it costs
 * and where it stands. The amount charged always comes from here, never from the request.
 */
final readonly class OrderForPayment
{
    public function __construct(
        public string $id,
        public string $customerId,
        public int $totalCents,
        public OrderStatus $status,
    ) {}
}
