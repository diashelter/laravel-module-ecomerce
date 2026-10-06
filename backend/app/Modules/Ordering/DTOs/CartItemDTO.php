<?php

declare(strict_types=1);

namespace App\Modules\Ordering\DTOs;

final readonly class CartItemDTO
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}
