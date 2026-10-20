<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\DTOs;

final readonly class CartItemDTO
{
    public function __construct(
        public string $productId,
        public int $quantity,
    ) {}
}
