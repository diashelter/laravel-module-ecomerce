<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Repositories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\CategoryIds;
use App\Modules\Shared\Repositories\BaseRepository;

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
