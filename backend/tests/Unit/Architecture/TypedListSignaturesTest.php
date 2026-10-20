<?php

use Modules\Catalog\Contracts\CatalogMetrics;
use Modules\Catalog\Contracts\ProductCatalog;
use Modules\Catalog\Core\DTOs\ProductDTO;
use Modules\Catalog\Persistence\Queries\ProductCatalogQuery;
use Modules\Catalog\Persistence\Repositories\ProductRepository;
use Modules\Customers\Core\UseCases\ListCustomersUseCase;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Contracts\CustomerMetrics;
use Modules\Inventory\Contracts\StockLevels;
use Modules\Inventory\Contracts\StockMetrics;
use Modules\Inventory\Contracts\StockReservation;
use Modules\Ordering\Contracts\CustomerOrderHistory;
use Modules\Ordering\Contracts\OrderMetrics;
use Modules\Ordering\Contracts\PayableOrders;
use Modules\Ordering\Core\DTOs\CartDTO;
use Modules\Ordering\Core\Services\CartValidationService;
use Modules\Ordering\Core\Services\CheckoutService;
use Modules\Ordering\Core\UseCases\PlaceOrderUseCase;
use Modules\Ordering\Core\UseCases\ValidateCartUseCase;
use Modules\Ordering\Persistence\Repositories\OrderRepository;

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
