<?php

use Modules\Catalog\Core\ValueObjects\ProductView;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Queries\ProductQuery;

beforeEach(fn () => $this->query = app(ProductQuery::class));

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
