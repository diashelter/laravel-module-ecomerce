<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** A canonical UUID v7 that is in no table: what the tests send as an unknown id. */
function unknownId(): string
{
    return Str::uuid7()->toString();
}

/**
 * Signs a customer in on the `customer` guard by id alone. Outside the Identity module the authenticated
 * customer is an id, so a module test needs no account behind it. Returns the id (a new UUID v7 when none is given).
 */
function signInAsCustomer(?string $id = null): string
{
    $id ??= Str::uuid7()->toString();
    test()->actingAs(new GenericUser(['id' => $id]), 'customer');

    return $id;
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
