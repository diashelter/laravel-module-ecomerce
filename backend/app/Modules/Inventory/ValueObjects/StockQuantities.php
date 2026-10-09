<?php

declare(strict_types=1);

namespace App\Modules\Inventory\ValueObjects;

use ArrayIterator;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * Units in stock per product. A product without a stock row has no entry in the query result,
 * so this class answers zero for it. Iterates only the products that have a row:
 * `product_id => quantity`, in the order of the query.
 *
 * @implements IteratorAggregate<int, int>
 */
final readonly class StockQuantities implements Countable, IteratorAggregate
{
    /** @var array<int, int> */
    private array $quantities;

    /** @param  Collection<int, int>  $quantitiesByProductId  `product_id => quantity`, as plucked from the query */
    public function __construct(Collection $quantitiesByProductId)
    {
        $this->quantities = $quantitiesByProductId->map(fn ($quantity) => (int) $quantity)->all();
    }

    public function of(int $productId): int
    {
        return $this->quantities[$productId] ?? 0;
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
