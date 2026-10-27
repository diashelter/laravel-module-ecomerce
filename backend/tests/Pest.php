<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Customers\Persistence\Models\CustomerAddress;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Inventory\Persistence\Models\Stock;
use Modules\Ordering\Core\Services\OrderCodeGenerator;
use Tests\TestCase;

/*
 * The tests of a module live in app-modules/<module>/tests (Unit and Feature) and use only that module, Shared
 * and Messaging. This folder is the composition suite: the flows that join real modules, the architecture, the
 * payload compatibility, the adapters and the seeders. Both are configured here, the only Pest.php Pest loads.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../app-modules/*/tests/Feature');

// MongoDB is not rolled back by RefreshDatabase: every feature test starts with both read model collections empty.
pest()->beforeEach(fn () => emptyReadModel())->in('Feature', '../app-modules/*/tests/Feature');

// Every test that uses the broker starts on a vhost with nothing declared in it.
pest()->beforeEach(fn () => resetBroker())->in('Feature/Messaging', '../app-modules/messaging/tests/Feature/Messaging');

// Unit tests boot the application (helpers, config) but never touch the database.
pest()->extend(TestCase::class)->in('Unit', '../app-modules/*/tests/Unit');

// Each module keeps the helpers of its own tests; the composition tests below may call any of them.
foreach (glob(__DIR__.'/../app-modules/*/tests/Helpers.php') as $helpers) {
    require_once $helpers;
}

/** A saved address of the customer's address book (SP, unless an override says otherwise). */
function addressOf(CustomerAccount $account, array $attributes = []): CustomerAddress
{
    return CustomerAddress::factory()->create(['customer_id' => $account->id, ...$attributes]);
}

/** Valid body for `POST|PUT /api/account/addresses`; pass overrides to change fields. */
function addressPayload(array $overrides = []): array
{
    return array_merge([
        'recipient_name' => 'Ana Souza',
        'postal_code' => '01310-100',
        'street' => 'Avenida Paulista',
        'number' => '1000',
        'complement' => 'Apto 12',
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
    ], $overrides);
}

/** A complete row for `orders`, written straight to the table with no model in between. */
function orderRow(string $customerId, array $overrides = []): array
{
    return array_merge([
        'id' => unknownId(),
        'code' => (new OrderCodeGenerator)->generate(),
        'customer_id' => $customerId,
        'total_cents' => 0,
        'shipping_cents' => 0,
        'delivery_business_days' => 2,
        'estimated_delivery_on' => null,
        'delivery_recipient_name' => 'Ana Souza',
        'delivery_postal_code' => '01310100',
        'delivery_street' => 'Avenida Paulista',
        'delivery_number' => '1000',
        'delivery_complement' => null,
        'delivery_district' => 'Bela Vista',
        'delivery_city' => 'São Paulo',
        'delivery_state' => 'SP',
        'status' => 'placed',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

/**
 * Replaces the order code draw with a fixed sequence, so a test decides which codes collide. The
 * returned generator counts the draws; asking for more codes than the sequence holds is an error.
 *
 * @param  list<string>  $codes
 */
function forceOrderCodes(array $codes): OrderCodeGenerator
{
    $generator = new class($codes) extends OrderCodeGenerator
    {
        public int $draws = 0;

        /** @param  list<string>  $codes */
        public function __construct(private array $codes) {}

        public function generate(): string
        {
            return $this->codes[$this->draws++] ?? throw new LogicException('The forced order codes ran out.');
        }
    };

    app()->instance(OrderCodeGenerator::class, $generator);

    return $generator;
}

function productWithStock(int $quantity, array $attributes = []): Product
{
    return Product::factory()->withStock($quantity)->create($attributes);
}

/** The stock row of a product, read from `stocks` by its `product_id` (the product has no relation to it). */
function stockOf(Product $product): Stock
{
    return Stock::query()->where('product_id', $product->id)->firstOrFail();
}

/** Valid admin product payload (price in cents); pass overrides to change fields. */
function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Monitor 27',
        'price_cents' => 129990,
        'description' => 'Monitor IPS',
        'image_url' => null,
        'status' => 'active',
        'category_ids' => Category::factory()->count(2)->create()->pluck('id')->all(),
        'stock_quantity' => 7,
    ], $overrides);
}
