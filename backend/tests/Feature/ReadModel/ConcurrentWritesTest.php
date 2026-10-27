<?php

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\DTOs\ProductDTO;
use Modules\Catalog\Core\DTOs\UpdateProductStatusDTO;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Core\UseCases\ChangeProductStatusUseCase;
use Modules\Catalog\Core\UseCases\UpdateProductUseCase;
use Modules\Catalog\Core\ValueObjects\CategoryIds;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Inventory\Core\DTOs\AdjustStockDTO;
use Modules\Inventory\Core\Enums\StockOperation;
use Modules\Inventory\Core\UseCases\AdjustStockUseCase;
use Modules\Ordering\Core\DTOs\CartDTO;
use Modules\Ordering\Core\DTOs\CartItemDTO;
use Modules\Ordering\Core\UseCases\PlaceOrderUseCase;

/*
 * Two writes on the same row, in processes released at the same instant, with data really committed (door 3 of the
 * storefront read model): the row lock gives the events strictly increasing versions in commit order, and the event
 * with the higher version carries the state the row holds after both.
 */

const CONCURRENT_ROUNDS = 20;

/** Removes everything the concurrent tests committed. The test database holds nothing else outside a test's transaction. */
function truncateCommittedData(): void
{
    DB::statement('TRUNCATE outbox_messages, orders, customers, customer_addresses, products, categories RESTART IDENTITY CASCADE');
}

afterEach(fn () => truncateCommittedData());

it('gives concurrent product writes increasing versions and the final row to the newest event', function () {
    commitTestTransaction();
    $category = Category::factory()->create();

    foreach (range(1, CONCURRENT_ROUNDS) as $round) {
        $product = productWithStock(4);
        $edit = new ProductDTO("Editado {$round}", 5000 + $round, 'Nova descrição', null, ProductStatus::Active, new CategoryIds($category->id));

        $failures = runConcurrently([
            fn () => app(ChangeProductStatusUseCase::class)->execute($product->id, new UpdateProductStatusDTO(ProductStatus::Inactive)),
            fn () => app(UpdateProductUseCase::class)->execute($product->id, $edit),
        ]);

        $messages = DB::table('outbox_messages')->whereIn('name', ['catalog.product_updated', 'catalog.product_status_changed'])
            ->get()->map(fn (object $message) => json_decode($message->payload, true))
            ->filter(fn (array $payload) => $payload['product_id'] === $product->id)
            ->sortBy('version')->values();
        $row = DB::table('products')->where('id', $product->id)->first();
        $newest = $messages->last();

        expect($failures)->toBe([], "round {$round}")
            ->and($messages->pluck('version')->all())->toBe([2, 3], "round {$round}")
            ->and($row->version)->toBe(3)
            ->and([$newest['name'], $newest['price_cents'], $newest['status'], $newest['category_ids']])
            ->toBe([$row->name, $row->price_cents, $row->status, [$category->id]], "round {$round}");
    }
});

it('gives a concurrent checkout and stock adjustment increasing versions and the final quantity to the newest event', function () {
    commitTestTransaction();
    $account = customer();
    $address = addressOf($account);

    foreach (range(1, CONCURRENT_ROUNDS) as $round) {
        $product = productWithStock(10);
        $stock = stockOf($product);

        $failures = runConcurrently([
            fn () => app(PlaceOrderUseCase::class)->execute($account->id, new CartDTO(new CartItemDTO($product->id, 3)), $address->id),
            fn () => app(AdjustStockUseCase::class)->execute($stock->id, new AdjustStockDTO(StockOperation::Increase, 5)),
        ]);

        $messages = DB::table('outbox_messages')->where('name', 'inventory.stock_changed')->get()
            ->map(fn (object $message) => json_decode($message->payload, true))
            ->filter(fn (array $payload) => $payload['stock_id'] === $stock->id)
            ->sortBy('version')->values();
        $row = DB::table('stocks')->where('id', $stock->id)->first();

        expect($failures)->toBe([], "round {$round}")
            ->and($messages->pluck('version')->all())->toBe([2, 3], "round {$round}")
            ->and($row->version)->toBe(3)
            ->and($row->quantity)->toBe(12)
            ->and($messages->last()['quantity'])->toBe($row->quantity, "round {$round}");
    }
});
