<?php

declare(strict_types=1);

namespace App\Modules\Catalog\ValueObjects;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * The products found by the catalog, keyed by id. Iterates `product_id => CatalogProduct`.
 *
 * @implements IteratorAggregate<int, CatalogProduct>
 */
final readonly class CatalogProducts implements Countable, IteratorAggregate
{
    /** @var array<int, CatalogProduct> */
    private array $products;

    public function __construct(CatalogProduct ...$products)
    {
        $byId = [];

        foreach ($products as $product) {
            $byId[$product->id] = $product;
        }

        $this->products = $byId;
    }

    public function find(int $productId): ?CatalogProduct
    {
        return $this->products[$productId] ?? null;
    }

    public function count(): int
    {
        return count($this->products);
    }

    /** @return Traversable<int, CatalogProduct> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->products);
    }
}
