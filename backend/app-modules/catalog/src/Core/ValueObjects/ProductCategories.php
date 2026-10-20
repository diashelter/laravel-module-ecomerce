<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\ValueObjects;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * The categories of one product, in the order the query returned them.
 *
 * @implements IteratorAggregate<int, ProductCategory>
 */
final readonly class ProductCategories implements Countable, IteratorAggregate
{
    /** @var list<ProductCategory> */
    private array $categories;

    public function __construct(ProductCategory ...$categories)
    {
        $this->categories = array_values($categories);
    }

    public function count(): int
    {
        return count($this->categories);
    }

    /** @return Traversable<int, ProductCategory> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->categories);
    }
}
