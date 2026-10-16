<?php

use App\Modules\Catalog\Contracts\CatalogMetrics;
use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\DTOs\ProductDTO;
use App\Modules\Catalog\Queries\ProductCatalogQuery;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Customers\UseCases\ListCustomersUseCase;
use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\Contracts\CustomerMetrics;
use App\Modules\Inventory\Contracts\StockLevels;
use App\Modules\Inventory\Contracts\StockMetrics;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Ordering\Contracts\CustomerOrderHistory;
use App\Modules\Ordering\Contracts\OrderMetrics;
use App\Modules\Ordering\Contracts\PayableOrders;
use App\Modules\Ordering\DTOs\CartDTO;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Ordering\Services\CartValidationService;
use App\Modules\Ordering\Services\CheckoutService;
use App\Modules\Ordering\UseCases\PlaceOrderUseCase;
use App\Modules\Ordering\UseCases\ValidateCartUseCase;

it('declares no array in the domain list signatures', function (string $class, ?string $method) {
    $reflection = new ReflectionClass($class);
    $methods = $method === null
        ? array_filter($reflection->getMethods(ReflectionMethod::IS_PUBLIC), fn (ReflectionMethod $m) => $m->class === $class)
        : [$reflection->getMethod($method)];

    expect($methods)->not->toBeEmpty();

    foreach ($methods as $target) {
        $types = [$target->getReturnType(), ...array_map(fn (ReflectionParameter $p) => $p->getType(), $target->getParameters())];

        foreach ($types as $type) {
            expect(str_contains((string) $type, 'array'))
                ->toBeFalse("{$class}::{$target->getName()} declares an array");
        }
    }
})->with([
    [CartDTO::class, null],
    [CartValidationService::class, null],
    [CheckoutService::class, null],
    [ValidateCartUseCase::class, null],
    [PlaceOrderUseCase::class, null],
    [ListCustomersUseCase::class, null],
    [StockReservation::class, null],
    [StockLevels::class, null],
    [ProductCatalog::class, null],
    [CatalogMetrics::class, null],
    [StockMetrics::class, null],
    [CustomerMetrics::class, null],
    [OrderMetrics::class, null],
    [PayableOrders::class, null],
    [CustomerOrderHistory::class, null],
    [CustomerAccounts::class, null],
    [ProductDTO::class, '__construct'],
    [ProductCatalogQuery::class, 'findMany'],
    [ProductRepository::class, 'syncCategories'],
    [OrderRepository::class, 'countPerCustomer'],
    [OrderRepository::class, 'createWithItems'],
]);
