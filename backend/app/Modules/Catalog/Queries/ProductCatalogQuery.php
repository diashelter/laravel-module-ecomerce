<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\CatalogProduct;
use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Catalog\ValueObjects\ProductIds;

/**
 * What the other modules may know about a product, as data. It never asks another module, so the
 * modules that depend on the catalog can depend on it without a cycle.
 */
class ProductCatalogQuery implements ProductCatalog
{
    public function findMany(ProductIds $ids): CatalogProducts
    {
        return new CatalogProducts(...Product::query()->whereIn('id', $ids->all())->get()->map(fn (Product $product) => new CatalogProduct(
            $product->id,
            $product->name,
            $product->image_url,
            $product->price_cents,
            $product->status,
        ))->all());
    }
}
