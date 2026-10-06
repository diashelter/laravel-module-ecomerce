<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;

beforeEach(fn () => $this->repository = app(ProductRepository::class));

it('filters the catalog by category slug', function () {
    $phones = Category::factory()->create(['name' => 'Celulares']);
    $books = Category::factory()->create(['name' => 'Livros']);
    $phone = productWithStock(1);
    $phone->categories()->attach($phones);
    productWithStock(1)->categories()->attach($books);

    $page = $this->repository->paginateCatalog('celulares', 'name', 12);

    expect($page->pluck('id')->all())->toBe([$phone->id]);
});

it('sorts the catalog with id as tie breaker', function (string $sort, array $expectedNames) {
    productWithStock(1, ['name' => 'B', 'price' => '20.00']);
    productWithStock(1, ['name' => 'A', 'price' => '30.00']);
    productWithStock(1, ['name' => 'C', 'price' => '10.00']);
    productWithStock(1, ['name' => 'D', 'price' => '10.00']);

    $page = $this->repository->paginateCatalog(null, $sort, 12);

    expect($page->pluck('name')->all())->toBe($expectedNames);
})->with([
    'name' => ['name', ['A', 'B', 'C', 'D']],
    'price ascending' => ['price_asc', ['C', 'D', 'B', 'A']],
    'price descending' => ['price_desc', ['A', 'B', 'C', 'D']],
]);

it('includes inactive products in the catalog and eager loads relations', function () {
    productWithStock(3);
    Product::factory()->inactive()->withStock(3)->create();

    $page = $this->repository->paginateCatalog(null, 'name', 12);

    expect($page->total())->toBe(2)
        ->and($page->first()->relationLoaded('categories'))->toBeTrue()
        ->and($page->first()->relationLoaded('stock'))->toBeTrue();
});

it('paginates the catalog with the given page size', function () {
    Product::factory()->count(5)->withStock(1)->create();

    $page = $this->repository->paginateCatalog(null, 'name', 2);

    expect($page->count())->toBe(2)
        ->and($page->total())->toBe(5)
        ->and($page->perPage())->toBe(2);
});

it('lists products for the admin newest first', function () {
    $first = productWithStock(1);
    $second = productWithStock(1);

    $page = $this->repository->paginateForAdmin(15);

    expect($page->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($page->first()->relationLoaded('stock'))->toBeTrue();
});

it('syncs the product categories', function () {
    $product = productWithStock(1);
    [$old, $kept, $new] = Category::factory()->count(3)->create();
    $product->categories()->attach([$old->id, $kept->id]);

    $this->repository->syncCategories($product, [$kept->id, $new->id]);

    expect($product->categories()->pluck('categories.id')->sort()->values()->all())
        ->toBe(collect([$kept->id, $new->id])->sort()->values()->all());
});

it('finds many products keyed by id', function () {
    $first = productWithStock(4);
    $second = productWithStock(2);
    productWithStock(1);

    $products = $this->repository->findManyKeyedById([$second->id, $first->id, 999999]);

    expect($products->keys()->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and($products->get($first->id)->relationLoaded('stock'))->toBeFalse();
});

it('optionally eager loads the stock when finding many products', function () {
    $product = productWithStock(4);

    $products = $this->repository->findManyKeyedById([$product->id], withStock: true);

    expect($products->get($product->id)->relationLoaded('stock'))->toBeTrue()
        ->and($products->get($product->id)->stock->quantity)->toBe(4);
});

it('counts products by status', function () {
    Product::factory()->count(2)->create();
    Product::factory()->inactive()->create();

    $counts = $this->repository->countByStatus();

    expect((int) $counts['active'])->toBe(2)
        ->and((int) $counts['inactive'])->toBe(1);
});
