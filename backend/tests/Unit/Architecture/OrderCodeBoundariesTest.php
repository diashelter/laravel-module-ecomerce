<?php

use Illuminate\Support\Facades\Route;

function isCodeName(string $name): bool
{
    return $name === 'code' || str_ends_with($name, 'Code');
}

it('keeps the order code out of the contracts events and routes', function () {
    $modules = realpath(__DIR__.'/../../../app/Modules');
    $contracts = glob("{$modules}/*/Contracts/*.php");
    $carriers = [...glob("{$modules}/*/Events/*.php"), ...glob("{$modules}/*/Jobs/*.php")];

    expect(count($contracts))->toBeGreaterThanOrEqual(11)
        ->and(count($carriers))->toBeGreaterThanOrEqual(6);

    $classOf = fn (string $file) => 'App\\Modules\\'.str_replace('/', '\\', substr($file, strlen($modules) + 1, -4));

    foreach ($contracts as $file) {
        foreach ((new ReflectionClass($classOf($file)))->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                expect(isCodeName($parameter->getName()))->toBeFalse("{$method->class}::{$method->getName()}() takes \${$parameter->getName()}");
            }
        }
    }

    foreach ($carriers as $file) {
        $class = $classOf($file);

        foreach ((new ReflectionClass($class))->getProperties() as $property) {
            expect(isCodeName($property->getName()))->toBeFalse("{$class} carries \${$property->getName()}");
        }
    }

    $uris = collect(Route::getRoutes()->getRoutes())->map->uri()->filter(fn (string $uri) => str_starts_with($uri, 'api/'));

    expect($uris->count())->toBeGreaterThan(30);

    foreach ($uris as $uri) {
        expect(str_contains(strtolower($uri), 'code'))->toBeFalse("{$uri} has the order code in its path");
    }
});
