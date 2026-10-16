<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Repositories\StockRepository;
use App\Modules\Inventory\ValueObjects\StockQuantities;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->repository = app(StockRepository::class));

it('creates the stock row of a product', function () {
    $product = Product::factory()->create();

    $this->repository->createForProduct($product->id, 7);

    expect(Stock::query()->where('product_id', $product->id)->pluck('quantity')->all())->toBe([7]);
});

it('locks the stock rows of the given products keyed by product id', function () {
    $first = productWithStock(5);
    $second = productWithStock(8);
    productWithStock(1);

    $stock = DB::transaction(fn () => $this->repository->lockForProducts(new ProductIds($second->id, $first->id)));

    expect($stock)->toBeInstanceOf(StockQuantities::class)
        ->and(iterator_to_array($stock))->toBe([$first->id => 5, $second->id => 8])
        ->and($stock->of($second->id))->toBe(8);
});

it('locks a single stock row by id', function () {
    $stock = stockOf(productWithStock(5));
    Stock::query()->whereKey($stock->id)->update(['quantity' => 9]);

    $locked = DB::transaction(fn () => $this->repository->lockById($stock->id));

    expect($locked->quantity)->toBe(9);
});

it('fails when locking a missing stock row', function () {
    DB::transaction(fn () => $this->repository->lockById(unknownId()));
})->throws(ModelNotFoundException::class);

it('decrements the stock quantity', function () {
    $product = productWithStock(5);
    $other = productWithStock(5);

    $this->repository->decrement($product->id, 2);

    expect(stockOf($product)->quantity)->toBe(3)
        ->and(stockOf($other)->quantity)->toBe(5);
});
