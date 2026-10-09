<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\ValueObjects\ProductIds;
use App\Modules\Inventory\Contracts\StockLevels;
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

    $stock = DB::transaction(fn () => $this->repository->lockForProducts(new ProductIds($second->id, $first->id)));

    expect($stock)->toBeInstanceOf(StockQuantities::class)
        ->and(iterator_to_array($stock))->toBe([$first->id => 5, $second->id => 8])
        ->and($stock->of($second->id))->toBe(8);
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
    $product = productWithStock(5);
    $other = productWithStock(5);

    $this->repository->decrement($product->id, 2);

    expect($product->stock->fresh()->quantity)->toBe(3)
        ->and($other->stock->fresh()->quantity)->toBe(5);
});

it('reads the stock quantities of the given products without locking', function () {
    $first = productWithStock(4);
    $second = productWithStock(9);
    $withoutStock = Product::factory()->create();

    DB::enableQueryLog();
    $stock = app(StockLevels::class)->quantitiesFor(new ProductIds($first->id, $second->id, $withoutStock->id));

    expect($stock->of($first->id))->toBe(4)
        ->and($stock->of($second->id))->toBe(9)
        ->and($stock->of($withoutStock->id))->toBe(0)
        ->and(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'for update')))->toBeEmpty();
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
