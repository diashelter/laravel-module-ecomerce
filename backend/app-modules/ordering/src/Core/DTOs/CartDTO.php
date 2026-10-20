<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\DTOs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Modules\Ordering\Core\ValueObjects\ProductQuantities;
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
