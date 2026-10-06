<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Repositories\CategoryRepository;

beforeEach(fn () => $this->repository = app(CategoryRepository::class));

it('lists categories ordered by name', function () {
    Category::factory()->create(['name' => 'Livros']);
    Category::factory()->create(['name' => 'Audio']);
    Category::factory()->create(['name' => 'Celulares']);

    $categories = $this->repository->allOrderedByName();

    expect($categories->pluck('name')->all())->toBe(['Audio', 'Celulares', 'Livros'])
        ->and($categories->first()->products_count)->toBeNull();
});

it('lists categories with their product count', function () {
    $phones = Category::factory()->create(['name' => 'Celulares']);
    Category::factory()->create(['name' => 'Livros']);
    productWithStock(1)->categories()->attach($phones);
    productWithStock(1)->categories()->attach($phones);

    $categories = $this->repository->allWithProductCount()->keyBy('name');

    expect($categories['Celulares']->products_count)->toBe(2)
        ->and($categories['Livros']->products_count)->toBe(0);
});

it('tells whether a category has products', function () {
    $used = Category::factory()->create();
    $empty = Category::factory()->create();
    productWithStock(1)->categories()->attach($used);

    expect($this->repository->hasProducts($used))->toBeTrue()
        ->and($this->repository->hasProducts($empty))->toBeFalse();
});
