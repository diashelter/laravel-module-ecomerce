<?php

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\CatalogProduct;
use App\Modules\Catalog\ValueObjects\CatalogProducts;
use App\Modules\Catalog\ValueObjects\ProductIds;

it('finds catalog products by id for other modules', function () {
    $active = productWithStock(3, ['name' => 'Teclado', 'price_cents' => 15990, 'image_url' => 'https://example.com/teclado.png']);
    $inactive = Product::factory()->inactive()->create(['name' => 'Mouse', 'price_cents' => 4990, 'image_url' => null]);

    $unknown = unknownId();

    $products = app(ProductCatalog::class)->findMany(new ProductIds($active->id, $inactive->id, $unknown));

    expect($products)->toBeInstanceOf(CatalogProducts::class)
        ->and($products)->toHaveCount(2)
        ->and($products->find($active->id))->toEqual(new CatalogProduct($active->id, 'Teclado', 'https://example.com/teclado.png', 15990, ProductStatus::Active))
        ->and($products->find($inactive->id))->toEqual(new CatalogProduct($inactive->id, 'Mouse', null, 4990, ProductStatus::Inactive))
        ->and($products->find($unknown))->toBeNull();
});
