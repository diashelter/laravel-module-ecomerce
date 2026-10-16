<?php

declare(strict_types=1);

namespace App\Modules\Inventory\ValueObjects;

use ArrayIterator;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * The stock rows of several products, keyed by product id. A product without a stock row has no
 * entry, so `of()` answers null for it. Iterates only the products that have a row:
 * `product_id => ProductStock`.
 *
 * @implements IteratorAggregate<string, ProductStock>
 */
final readonly class ProductStocks implements Countable, IteratorAggregate
{
    /** @var array<string, ProductStock> */
    private array $stocks;

    /** @param  Collection<string, ProductStock>  $stocksByProductId  `product_id => ProductStock` */
    public function __construct(Collection $stocksByProductId)
    {
        $this->stocks = $stocksByProductId->all();
    }

    public function of(string $productId): ?ProductStock
    {
        return $this->stocks[$productId] ?? null;
    }

    public function count(): int
    {
        return count($this->stocks);
    }

    /** @return Traversable<string, ProductStock> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->stocks);
    }
}
