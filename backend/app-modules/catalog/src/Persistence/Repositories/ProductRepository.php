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

    /**
     * The ids of the categories of a product in ascending order: the order the events carry and the storefront shows.
     *
     * @return list<string>
     */
    public function categoryIdsOf(string $productId): array
    {
        // Only the id is needed: the relation query runs on the pivot, without reading the product.
        return (new Product)->forceFill(['id' => $productId])->categories()
            ->orderBy('categories.id')->pluck('categories.id')->all();
    }

    public function syncCategories(Product $product, CategoryIds $categoryIds): void
    {
        $product->categories()->sync($categoryIds->all());
    }
}
