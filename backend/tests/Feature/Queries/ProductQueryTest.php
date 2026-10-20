<?php

use Modules\Catalog\Core\ValueObjects\ProductView;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Catalog\Persistence\Queries\ProductQuery;

beforeEach(fn () => $this->query = app(ProductQuery::class));

it('filters the catalog by category slug', function () {
    $phones = Category::factory()->create(['name' => 'Celulares']);
    $books = Category::factory()->create(['name' => 'Livros']);
    $phone = productWithStock(1);
    $phone->categories()->attach($phones);
    productWithStock(1)->categories()->attach($books);

    $page = $this->query->paginateCatalog('celulares', 'name', 12);

    expect($page->map(fn (ProductView $view) => $view->product->id)->all())->toBe([$phone->id]);
});

it('sorts the catalog with id as tie breaker', function (string $sort, array $expectedNames) {
    productWithStock(1, ['name' => 'B', 'price_cents' => 2000]);
    productWithStock(1, ['name' => 'A', 'price_cents' => 3000]);
    productWithStock(1, ['name' => 'C', 'price_cents' => 1000]);
    productWithStock(1, ['name' => 'D', 'price_cents' => 1000]);

    $page = $this->query->paginateCatalog(null, $sort, 12);

    expect($page->map(fn (ProductView $view) => $view->product->name)->all())->toBe($expectedNames);
})->with([
    'name' => ['name', ['A', 'B', 'C', 'D']],
    'price ascending' => ['price_asc', ['C', 'D', 'B', 'A']],
    'price descending' => ['price_desc', ['A', 'B', 'C', 'D']],
]);

it('includes inactive products in the catalog with categories and stock', function () {
    $active = productWithStock(3);
    $active->categories()->attach(Category::factory()->count(2)->create());
    $inactive = Product::factory()->inactive()->withStock(3)->create();

    $page = $this->query->paginateCatalog(null, 'name', 12);
    $views = $page->keyBy(fn (ProductView $view) => $view->product->id);

    expect($page->total())->toBe(2)
        ->and($views[$active->id]->categories)->toHaveCount(2)
        ->and($views[$active->id]->stock->quantity)->toBe(3)
        ->and($views[$active->id]->availability()->isAvailable())->toBeTrue()
        ->and($views[$inactive->id]->stock->quantity)->toBe(3)
        ->and($views[$inactive->id]->availability()->isAvailable())->toBeFalse();
});

it('paginates the catalog with the given page size', function () {
    Product::factory()->count(5)->withStock(1)->create();

    $page = $this->query->paginateCatalog(null, 'name', 2);

    expect($page->count())->toBe(2)
        ->and($page->total())->toBe(5)
        ->and($page->perPage())->toBe(2);
});

it('lists products for the admin newest first', function () {
    $first = productWithStock(1);
    $second = productWithStock(1);

    $page = $this->query->paginateForAdmin(15);

    expect($page->map(fn (ProductView $view) => $view->product->id)->all())->toBe([$second->id, $first->id])
        ->and($page->first()->stock->quantity)->toBe(1);
});

it('finds a product with its categories and stock', function () {
    $product = productWithStock(6);
    $product->categories()->attach(Category::factory()->create(['name' => 'Livros']));

    $view = $this->query->find($product->id);

    expect($view->product->id)->toBe($product->id)
        ->and(array_map(fn ($category) => $category->slug, iterator_to_array($view->categories)))->toBe(['livros'])
        ->and($view->stock->id)->toBe(stockOf($product)->id)
        ->and($view->availability()->availableQuantity())->toBe(6);
});

it('completes a written product with its categories and stock without reading it again', function () {
    $product = productWithStock(4);
    $product->categories()->attach(Category::factory()->count(2)->create());
    $record = $product->toRecord();

    $queries = queriesDuring(fn () => $this->view = $this->query->complete($record));

    expect($this->view->product)->toBe($record)
        ->and($this->view->categories)->toHaveCount(2)
        ->and($this->view->stock->quantity)->toBe(4)
        ->and($queries)->toHaveCount(2)
        ->and(collect($queries)->filter(fn (string $sql) => str_contains($sql, 'from "products"')))->toBeEmpty();
});
