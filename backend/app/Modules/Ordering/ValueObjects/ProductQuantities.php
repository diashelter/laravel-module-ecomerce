<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Ordering\DTOs\CartItemDTO;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Total quantity per product: one entry per product_id, sorted by product_id (the checkout
 * relies on this order to lock stock rows without deadlocks). Iterates `product_id => quantity`.
 *
 * @implements IteratorAggregate<int, int>
 */
final readonly class ProductQuantities implements Countable, IteratorAggregate
{
    /** @var array<int, int> */
    private array $quantities;

    public function __construct(CartItemDTO ...$items)
    {
        $quantities = [];

        foreach ($items as $item) {
            $quantities[$item->productId] = ($quantities[$item->productId] ?? 0) + $item->quantity;
        }

        ksort($quantities);

        $this->quantities = $quantities;
    }

    public function productIds(): ProductIds
    {
        return new ProductIds(...array_keys($this->quantities));
    }

    public function count(): int
    {
        return count($this->quantities);
    }

    /** @return Traversable<int, int> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->quantities);
    }
}
