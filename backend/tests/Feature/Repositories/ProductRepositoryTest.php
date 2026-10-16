<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Catalog\ValueObjects\CategoryIds;

beforeEach(fn () => $this->repository = app(ProductRepository::class));

it('syncs the product categories', function () {
    $product = productWithStock(1);
    [$old, $kept, $new] = Category::factory()->count(3)->create();
    $product->categories()->attach([$old->id, $kept->id]);

    $this->repository->syncCategories($product, new CategoryIds($kept->id, $new->id));

    expect($product->categories()->pluck('categories.id')->sort()->values()->all())
        ->toBe(collect([$kept->id, $new->id])->sort()->values()->all());
});
