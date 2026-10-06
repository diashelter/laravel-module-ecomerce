<?php

declare(strict_types=1);

namespace App\Modules\Ordering\DTOs;

/**
 * Only product ids and quantities: prices, names and totals are always read from the database.
 */
final readonly class CartDTO
{
    /** @param  list<CartItemDTO>  $items */
    public function __construct(public array $items) {}

    /**
     * Groups the items into [product_id => total quantity], sorted by product_id
     * (the checkout relies on this order to lock stock rows without deadlocks).
     *
     * @return array<int, int>
     */
    public function quantitiesByProduct(): array
    {
        $quantities = [];

        foreach ($this->items as $item) {
            $quantities[$item->productId] = ($quantities[$item->productId] ?? 0) + $item->quantity;
        }

        ksort($quantities);

        return $quantities;
    }
}
