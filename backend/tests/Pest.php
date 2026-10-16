<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Services\OrderCodeGenerator;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResult;
use App\Modules\Payment\Gateways\FakePaymentGateway;
use App\Modules\Shared\Messaging\EventTransport;
use App\Modules\Shared\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Outbox\Fixtures\SpyTransport;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests boot the application (helpers, config) but never touch the database.
pest()->extend(TestCase::class)->in('Unit');

function customer(array $attributes = []): CustomerAccount
{
    return CustomerAccount::factory()->create($attributes);
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

/** A canonical UUID v7 that is in no table: what the tests send as an unknown id. */
function unknownId(): string
{
    return Str::uuid7()->toString();
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

/**
 * The SQL of every query the closure runs, in order. Sign in and create the data before calling it,
 * so only the request itself is counted.
 *
 * @return list<string>
 */
function queriesDuring(Closure $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();

    return array_column(DB::getQueryLog(), 'query');
}

function admin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}

function support(array $attributes = []): User
{
    return User::factory()->support()->create($attributes);
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

/**
 * Asserts the standard API error body. The request_id must match the X-Request-ID header.
 *
 * @param  array<string, list<string>>  $errors
 */
function assertApiError(TestResponse $response, string $code, string $message, array $errors = []): TestResponse
{
    return $response->assertExactJson([
        'code' => $code,
        'message' => $message,
        'errors' => $errors,
        'request_id' => $response->headers->get('X-Request-ID'),
    ]);
}

/**
 * Binds a PaymentGateway double that records every charge it receives. It answers like the
 * fake gateway unless $answer (which receives the ChargeRequest) decides the result.
 */
function spyPaymentGateway(?Closure $answer = null): object
{
    $spy = new class($answer) implements PaymentGateway
    {
        /** @var list<ChargeRequest> */
        public array $charges = [];

        public function __construct(private readonly ?Closure $answer) {}

        public function charge(ChargeRequest $request): ChargeResult
        {
            $this->charges[] = $request;

            return $this->answer !== null
                ? ($this->answer)($request)
                : (new FakePaymentGateway)->charge($request);
        }
    };

    app()->instance(PaymentGateway::class, $spy);

    return $spy;
}

/**
 * The outbox messages recorded under an event name, oldest first.
 *
 * @return Collection<int, OutboxMessage>
 */
function outboxMessages(string $name): Collection
{
    return OutboxMessage::query()->where('name', $name)->orderBy('occurred_at')->orderBy('id')->get();
}

/** One `php artisan outbox:relay --once`: hands every pending message to the transport. Returns the exit code. */
function relayOnce(): int
{
    return Artisan::call('outbox:relay', ['--once' => true]);
}

/**
 * Runs the relay until no message is pending. With the sync queue each cycle runs the consumers,
 * which may record the next events of the chain.
 */
function relayUntilIdle(): void
{
    for ($cycle = 1; OutboxMessage::query()->whereNull('published_at')->exists(); $cycle++) {
        expect($cycle)->toBeLessThanOrEqual(10, 'The outbox never went idle.')
            ->and(relayOnce())->toBe(0);
    }
}

/**
 * Binds a spy as the event transport the relay publishes through. It refuses the message ids in
 * $failing with RuntimeException('transport down').
 *
 * @param  list<string>  $failing
 */
function spyTransport(array $failing = [], ?Closure $onPublish = null): SpyTransport
{
    $spy = new SpyTransport($failing, $onPublish);
    app()->instance(EventTransport::class, $spy);

    return $spy;
}
