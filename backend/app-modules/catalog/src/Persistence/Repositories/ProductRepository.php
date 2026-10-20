<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\Repositories;

use Modules\Catalog\Core\ValueObjects\CategoryIds;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Shared\Repositories\BaseRepository;

/**
 * The catalog's writes. The reads that serve a route live in the Queries folder.
 *
 * @extends BaseRepository<Product>
 */
class ProductRepository extends BaseRepository
{
    protected function model(): string
    {
        return Product::class;
    }

    public function syncCategories(Product $product, CategoryIds $categoryIds): void
    {
        $product->categories()->sync($categoryIds->all());
    }
}
