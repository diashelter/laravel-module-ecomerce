<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Repositories\StockRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->repository = app(StockRepository::class));

it('creates the stock row of a product', function () {
    $product = Product::factory()->create();

    $this->repository->createForProduct($product->id, 7);

    expect(Stock::query()->where('product_id', $product->id)->pluck('quantity')->all())->toBe([7]);
});

it('paginates stocks by quantity ascending', function () {
    $high = productWithStock(30)->stock;
    $low = productWithStock(1)->stock;
    $middle = productWithStock(10)->stock;

    $page = $this->repository->paginateByQuantity(20);

    expect($page->pluck('id')->all())->toBe([$low->id, $middle->id, $high->id])
        ->and($page->first()->relationLoaded('product'))->toBeTrue();
});

it('locks the stock rows of the given products keyed by product id', function () {
    $first = productWithStock(5);
    $second = productWithStock(8);
    productWithStock(1);

    $stocks = DB::transaction(fn () => $this->repository->lockForProducts(new ProductIds($second->id, $first->id)));

    expect($stocks->keys()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and($stocks->get($second->id)->quantity)->toBe(8);
});

it('locks a single stock row by id', function () {
    $stock = productWithStock(5)->stock;
    Stock::query()->whereKey($stock->id)->update(['quantity' => 9]);

    $locked = DB::transaction(fn () => $this->repository->lockById($stock->id));

    expect($locked->quantity)->toBe(9);
});

it('fails when locking a missing stock row', function () {
    DB::transaction(fn () => $this->repository->lockById(999999));
})->throws(ModelNotFoundException::class);

it('decrements the stock quantity', function () {
    $stock = productWithStock(5)->stock;

    $this->repository->decrement($stock, 3);

    expect($stock->fresh()->quantity)->toBe(2);
});

it('counts products in stock and the total units', function () {
    productWithStock(10);
    productWithStock(5);
    productWithStock(0);

    expect($this->repository->countInStock())->toBe(2)
        ->and($this->repository->totalUnits())->toBe(15);
});

it('returns zero units when there is no stock', function () {
    expect($this->repository->totalUnits())->toBe(0);
});
