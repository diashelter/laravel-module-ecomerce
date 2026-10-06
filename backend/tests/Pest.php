<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests boot the application (helpers, config) but never touch the database.
pest()->extend(TestCase::class)->in('Unit');

function customer(array $attributes = []): User
{
    return User::factory()->customer()->create($attributes);
}

function admin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
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
