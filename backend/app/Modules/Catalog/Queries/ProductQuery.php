<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\ProductCategories;
use App\Modules\Catalog\ValueObjects\ProductCategory;
use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Catalog\ValueObjects\ProductRecord;
use App\Modules\Catalog\ValueObjects\ProductView;
use App\Modules\Inventory\Contracts\StockLevels;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * The reads of the storefront and of the admin product pages. It reads the catalog tables and asks
 * the inventory, by id and once for the whole page, for the stock: the page size never changes the
 * number of queries.
 */
class ProductQuery
{
    public function __construct(private readonly StockLevels $stock) {}

    /**
     * Public catalog: active AND inactive products, filtered and sorted in the database.
     *
     * @return LengthAwarePaginator<int, ProductView>
     */
    public function paginateCatalog(?string $categorySlug, string $sort, int $perPage): LengthAwarePaginator
    {
        return $this->viewsOf(Product::query()
            ->with('categories')
            ->when($categorySlug, fn (Builder $query, string $slug) => $query->whereHas(
                'categories',
                fn (Builder $categories) => $categories->where('slug', $slug),
            ))
            ->tap(fn (Builder $query) => match ($sort) {
                'price_asc' => $query->orderBy('price_cents')->orderBy('id'),
                'price_desc' => $query->orderByDesc('price_cents')->orderBy('id'),
                default => $query->orderBy('name')->orderBy('id'),
            })
            ->paginate($perPage));
    }

    /** @return LengthAwarePaginator<int, ProductView> */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return $this->viewsOf(Product::query()->with('categories')->latest('id')->paginate($perPage));
    }

    /**
     * The product with its categories and stock, for the pages that show it.
     *
     * @throws ModelNotFoundException
     */
    public function find(string $id): ProductView
    {
        $product = Product::query()->with('categories')->findOrFail($id);

        return new ProductView($product->toRecord(), $this->categoriesOf($product), $this->stock->stocksFor(new ProductIds($id))->of($id));
    }

    /**
     * Completes what a write left behind with the categories and the stock, without reading the
     * product again.
     */
    public function complete(ProductRecord $record): ProductView
    {
        // Only the id is needed: the relation query runs on the pivot, without reading the product.
        $categories = (new Product)->forceFill(['id' => $record->id])->categories()->get();

        return new ProductView(
            $record,
            new ProductCategories(...$categories->map(fn (Category $category) => new ProductCategory($category->id, $category->name, $category->slug))->all()),
            $this->stock->stocksFor(new ProductIds($record->id))->of($record->id),
        );
    }

    /**
     * @param  Paginator<int, Product>  $page
     * @return LengthAwarePaginator<int, ProductView>
     */
    private function viewsOf(Paginator $page): LengthAwarePaginator
    {
        $stocks = $this->stock->stocksFor(new ProductIds(...$page->pluck('id')->all()));

        return $page->through(fn (Product $product) => new ProductView($product->toRecord(), $this->categoriesOf($product), $stocks->of($product->id)));
    }

    private function categoriesOf(Product $product): ProductCategories
    {
        return new ProductCategories(...$product->categories->map(fn (Category $category) => new ProductCategory($category->id, $category->name, $category->slug))->all());
    }
}
