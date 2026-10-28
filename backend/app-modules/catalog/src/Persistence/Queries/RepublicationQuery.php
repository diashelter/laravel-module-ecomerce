<?php

declare(strict_types=1);

namespace Modules\Catalog\Persistence\Queries;

use Generator;
use Modules\Catalog\Core\ValueObjects\CategoryRecord;
use Modules\Catalog\Core\ValueObjects\ProductSnapshot;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;

/** What the rebuild of the storefront republishes: every category, and every product with its categories. */
class RepublicationQuery
{
    private const PAGE = 200;

    /** @return Generator<int, CategoryRecord> */
    public function categories(): Generator
    {
        $after = null;

        while (($page = Category::query()->when($after, fn ($query, string $id) => $query->where('id', '>', $id))->orderBy('id')->limit(self::PAGE)->get())->isNotEmpty()) {
            foreach ($page as $category) {
                yield new CategoryRecord($category->id, $category->name, $category->slug, $category->version);
            }

            $after = $page->last()->id;
        }
    }

    /** @return Generator<int, string> */
    public function productIds(): Generator
    {
        $after = null;

        while (($page = Product::query()->when($after, fn ($query, string $id) => $query->where('id', '>', $id))->orderBy('id')->limit(self::PAGE)->pluck('id'))->isNotEmpty()) {
            yield from $page;

            $after = $page->last();
        }
    }

    /**
     * The product and its category ids from one statement, so the ids belong to the version the row carries (a
     * statement sees one snapshot of the database, whatever the isolation level of the transaction around it). Null
     * when the product no longer exists.
     */
    public function snapshotOf(string $productId): ?ProductSnapshot
    {
        $product = Product::query()
            ->select('products.*')
            ->selectRaw("(select coalesce(json_agg(category_id order by category_id), '[]'::json) from category_product where product_id = products.id) as category_ids")
            ->find($productId);

        if ($product === null) {
            return null;
        }

        return new ProductSnapshot($product->toRecord(), json_decode($product->category_ids, true));
    }
}
