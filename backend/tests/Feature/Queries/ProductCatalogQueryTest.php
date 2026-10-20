<?php

use Modules\Catalog\Contracts\ProductCatalog;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Core\ValueObjects\CatalogProduct;
use Modules\Catalog\Core\ValueObjects\CatalogProducts;
use Modules\Catalog\Core\ValueObjects\ProductIds;
use Modules\Catalog\Persistence\Models\Product;

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
