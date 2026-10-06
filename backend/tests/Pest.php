<?php

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
