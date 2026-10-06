<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Repositories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @extends BaseRepository<Product>
 */
class ProductRepository extends BaseRepository
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
                'price_asc' => $query->orderBy('price')->orderBy('id'),
                'price_desc' => $query->orderByDesc('price')->orderBy('id'),
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

    /** @param  list<int>  $categoryIds */
    public function syncCategories(Product $product, array $categoryIds): void
    {
        $product->categories()->sync($categoryIds);
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    public function findManyKeyedById(array $ids, bool $withStock = false): Collection
    {
        return $this->query()
            ->when($withStock, fn (Builder $query) => $query->with('stock'))
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
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
