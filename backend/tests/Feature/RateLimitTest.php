<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Every throttled /api route: method, uri as the route table writes it, limit per minute and
 * whether a guest reaches the throttle (a route behind the login answers 401 before counting).
 * The route table test below keeps this list equal to the routes.
 *
 * @return array<string, array{string, string, int, bool}>
 */
function throttledRoutes(): array
{
    return [
        'registration' => ['POST', 'api/auth/register', 10, true],
        'customer login' => ['POST', 'api/auth/login', 10, true],
        'staff login' => ['POST', 'api/admin/auth/login', 10, true],
        'cart validation' => ['POST', 'api/cart/validate', 60, true],
        'shipping quote' => ['GET', 'api/shipping/quote', 60, true],
        'checkout' => ['POST', 'api/orders', 20, false],
        'payment' => ['POST', 'api/orders/{order}/payment', 20, false],
    ];
}

/** One request with an empty body: the throttle counts it before any validation or lookup. */
function hitThrottledRoute(string $method, string $uri): TestResponse
{
    return test()->json($method, '/'.str_replace('{order}', unknownId(), $uri));
}

/**
 * Exhausts one route, then proves that only that route answers 429: each throttled route
 * counts its own requests, so the client's other routes keep their whole limit.
 */
function exhaustOnlyThisRoute(string $method, string $uri, int $limit, bool $asGuest): void
{
    foreach (range(1, $limit) as $attempt) {
        expect(hitThrottledRoute($method, $uri)->status())->not->toBe(429, "{$method} {$uri} answered 429 on attempt {$attempt}");
    }

    hitThrottledRoute($method, $uri)->assertTooManyRequests();

    foreach (throttledRoutes() as [$otherMethod, $otherUri, , $guest]) {
        if ($otherUri === $uri || ($asGuest && ! $guest)) {
            continue;
        }

        expect(hitThrottledRoute($otherMethod, $otherUri)->status())
            ->not->toBe(429, "{$otherMethod} {$otherUri} shares the counter of {$method} {$uri}");
    }
}

it('names a counter of its own on every throttled api route', function () {
    $routes = [];
    $counters = [];

    foreach (Route::getRoutes() as $route) {
        foreach ($route->middleware() as $middleware) {
            if (! str_starts_with($middleware, 'throttle:')) {
                continue;
            }

            [$limit, , $counter] = array_pad(explode(',', Str::after($middleware, 'throttle:')), 3, '');
            $routes[] = [$route->methods()[0], $route->uri(), (int) $limit, ! in_array('auth:customer', $route->middleware(), true)];
            $counters["{$route->methods()[0]} {$route->uri()}"] = $counter;
        }
    }

    expect($routes)->toEqualCanonicalizing(array_values(throttledRoutes()));

    foreach ($counters as $route => $counter) {
        expect($counter)->not->toBe('', "{$route} shares the default counter: name it in the third throttle parameter");
    }

    expect(array_unique($counters))->toBe($counters, 'two throttled routes share a counter name');
});

it('keeps every other throttled route open to a guest who exhausted one', function (string $method, string $uri, int $limit) {
    exhaustOnlyThisRoute($method, $uri, $limit, asGuest: true);
})->with(array_filter(throttledRoutes(), fn (array $route) => $route[3]));

it('keeps every other throttled route open to a customer who exhausted one', function (string $method, string $uri, int $limit) {
    $this->actingAs(customer());

    exhaustOnlyThisRoute($method, $uri, $limit, asGuest: false);
})->with(throttledRoutes());
