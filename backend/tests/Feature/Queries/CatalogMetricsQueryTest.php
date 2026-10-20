<?php

use Modules\Catalog\Contracts\CatalogMetrics;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Persistence\Models\Product;

it('counts products by status', function () {
    Product::factory()->count(2)->create();
    Product::factory()->inactive()->create();

    $counts = app(CatalogMetrics::class)->productCountsByStatus();

    expect($counts->of(ProductStatus::Active))->toBe(2)
        ->and($counts->of(ProductStatus::Inactive))->toBe(1)
        ->and($counts->total())->toBe(3);
});

it('answers zero for a status without products', function () {
    Product::factory()->create();

    $counts = app(CatalogMetrics::class)->productCountsByStatus();

    expect($counts->of(ProductStatus::Inactive))->toBe(0)
        ->and($counts->total())->toBe(1);
});
