<?php

use Modules\Catalog\Core\ValueObjects\CategoryIds;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Repositories\ProductRepository;

beforeEach(fn () => $this->repository = app(ProductRepository::class));

it('syncs the product categories', function () {
    $product = productWithStock(1);
    [$old, $kept, $new] = Category::factory()->count(3)->create();
    $product->categories()->attach([$old->id, $kept->id]);

    $this->repository->syncCategories($product, new CategoryIds($kept->id, $new->id));

    expect($product->categories()->pluck('categories.id')->sort()->values()->all())
        ->toBe(collect([$kept->id, $new->id])->sort()->values()->all());
});
