<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResult;
use App\Modules\Payment\Gateways\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

/** A complete row for `orders`, written straight to the table with no model in between. */
function orderRow(int $customerId, array $overrides = []): array
{
    return array_merge([
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
