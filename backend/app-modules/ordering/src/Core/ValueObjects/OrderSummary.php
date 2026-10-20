<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\ValueObjects;

use Carbon\CarbonImmutable;
use Modules\Ordering\Core\Enums\OrderStatus;

/**
 * An order as the account screens list it (see CustomerOrderHistory): no items, no address.
 * `code` is what the screens show; `id` is what the URLs carry.
 */
final readonly class OrderSummary
{
    public function __construct(
        public string $id,
        public string $code,
        public OrderStatus $status,
        public int $totalCents,
        public CarbonImmutable $createdAt,
    ) {}
}
