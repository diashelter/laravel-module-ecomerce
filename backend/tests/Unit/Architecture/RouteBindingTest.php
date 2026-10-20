<?php

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Modules\Shared\Support\RecordId;

/**
 * No route of the API resolves a record: it carries ids, and the use case or the repository finds
 * the record (with the owner, for the customer's routes). One expectation per rule, so a violation
 * of one rule never hides behind another.
 *
 * @return list<RoutingRoute> the routes under `api/` that have a parameter
 */
function apiRoutesWithParameters(): array
{
    $routes = array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/') && $route->parameterNames() !== [],
    ));

    // Not empty by accident: a filter that matched nothing would pass every rule.
    expect(count($routes))->toBeGreaterThanOrEqual(19);

    return $routes;
}

it('resolves no record by implicit binding in any api route', function () {
    foreach (apiRoutesWithParameters() as $route) {
        $bound = array_map(
            fn (ReflectionParameter $parameter) => $parameter->getName(),
            $route->signatureParameters(['subClass' => UrlRoutable::class]),
        );

        expect($bound)->toBe([], "{$route->uri()} binds ".implode(', ', $bound).' by implicit binding');
    }
});

it('resolves no record by explicit binding in any api route', function () {
    foreach (apiRoutesWithParameters() as $route) {
        foreach ($route->parameterNames() as $name) {
            expect(Route::getBindingCallback($name))->toBeNull("{$route->uri()} binds {{$name}} with Route::bind");
        }
    }
});

it('constrains every api route parameter to the canonical id', function () {
    foreach (apiRoutesWithParameters() as $route) {
        foreach ($route->parameterNames() as $name) {
            expect($route->wheres[$name] ?? null)->toBe(RecordId::PATTERN, "{$route->uri()} does not constrain {{$name}} to the canonical id");
        }
    }
});
