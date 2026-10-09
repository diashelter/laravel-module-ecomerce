<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Repositories;

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\CatalogProduct;
use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Catalog\ValueObjects\CategoryIds;
use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @extends BaseRepository<Product>
 */
class ProductRepository extends BaseRepository implements ProductCatalog
{
    protected function model(): string
    {
        return Product::class;
    }

    /**
     * Public catalog: active AND inactive products, filtered and sorted in the database.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateCatalog(?string $categorySlug, string $sort, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with(['categories', 'stock'])
            ->when($categorySlug, fn (Builder $query, string $slug) => $query->whereHas(
                'categories',
                fn (Builder $categories) => $categories->where('slug', $slug),
            ))
            ->tap(fn (Builder $query) => match ($sort) {
                'price_asc' => $query->orderBy('price_cents')->orderBy('id'),
                'price_desc' => $query->orderByDesc('price_cents')->orderBy('id'),
                default => $query->orderBy('name')->orderBy('id'),
            })
            ->paginate($perPage);
    }

    /** @return LengthAwarePaginator<int, Product> */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with(['categories', 'stock'])
            ->latest('id')
            ->paginate($perPage);
    }

    public function syncCategories(Product $product, CategoryIds $categoryIds): void
    {
        $product->categories()->sync($categoryIds->all());
    }

    public function findMany(ProductIds $ids): CatalogProducts
    {
        return new CatalogProducts(...$this->query()->whereIn('id', $ids->all())->get()->map(fn (Product $product) => new CatalogProduct(
            $product->id,
            $product->name,
            $product->image_url,
            $product->price_cents,
            $product->isActive(),
        ))->all());
    }

    /** @return BaseCollection<string, int> [status => total] */
    public function countByStatus(): BaseCollection
    {
        return $this->query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
    }
}
