<?php

use Illuminate\Support\Str;

it('generates a request id for every response', function () {
    $response = $this->getJson('/api/categories')->assertOk();

    expect(Str::isUuid($response->headers->get('X-Request-ID')))->toBeTrue();
});

it('reuses a valid request id sent by an upstream proxy', function () {
    $this->withHeader('X-Request-ID', 'proxy-id-1234')
        ->getJson('/api/categories')
        ->assertHeader('X-Request-ID', 'proxy-id-1234');
});

it('replaces request ids that are not safe to log', function (string $incoming) {
    $response = $this->withHeader('X-Request-ID', $incoming)->getJson('/api/categories');

    expect(Str::isUuid($response->headers->get('X-Request-ID')))->toBeTrue();
})->with(['too short' => 'abc', 'invalid characters' => 'id with spaces <script>']);

it('returns the request id in the error body', function () {
    $response = $this->withHeader('X-Request-ID', 'trace-me-please')->getJson('/api/products/999999');

    expect($response->json('request_id'))->toBe('trace-me-please');
});
