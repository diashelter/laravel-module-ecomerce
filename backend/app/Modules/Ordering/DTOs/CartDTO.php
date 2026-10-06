<?php

declare(strict_types=1);

namespace App\Modules\Ordering\DTOs;

use App\Modules\Ordering\ValueObjects\ProductQuantities;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Only product ids and quantities: prices, names and totals are always read from the database.
 *
 * @implements IteratorAggregate<int, CartItemDTO>
 */
final readonly class CartDTO implements Countable, IteratorAggregate
{
    /** @var list<CartItemDTO> */
    private array $items;

    public function __construct(CartItemDTO ...$items)
    {
        $this->items = array_values($items);
    }

    /** Groups the items by product (see ProductQuantities). */
    public function quantities(): ProductQuantities
    {
        return new ProductQuantities(...$this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, CartItemDTO> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
