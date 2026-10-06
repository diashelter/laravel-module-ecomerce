<?php

use App\Modules\Inventory\Contracts\StockInitializer;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Inventory\Repositories\StockRepository;
use App\Modules\Ordering\Repositories\OrderRepository;

/*
 * Boundaries between the modules (see docs/domain-analysis.md).
 *
 * Always one namespace per expectation: with an array of namespaces, `not->toUse` never fails.
 * And an expectation on a namespace that does not exist passes without checking anything,
 * so the last test makes sure every module named here still exists.
 */

const MODULES = ['Backoffice', 'Catalog', 'Customers', 'Fulfillment', 'Identity', 'Inventory', 'Ordering', 'Payment', 'Shared'];

// Inventory: other modules use its contracts, never its repository (the dashboard read model may read it).
foreach (array_diff(MODULES, ['Inventory', 'Backoffice']) as $module) {
    arch("{$module} reaches the inventory only through its contracts")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse(StockRepository::class);
}

arch('the inventory contracts are interfaces')
    ->expect('App\Modules\Inventory\Contracts')
    ->toBeInterfaces();

// Identity: the account does not know about orders.
arch('identity does not depend on ordering')
    ->expect('App\Modules\Identity')
    ->not->toUse('App\Modules\Ordering');

// Payment and fulfillment only publish events: the order status is changed by ordering alone,
// and they do not know each other.
foreach (['Payment', 'Fulfillment'] as $module) {
    arch("{$module} never changes the order status itself")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse(OrderRepository::class);
}

arch('payment does not know fulfillment')
    ->expect('App\Modules\Payment')
    ->not->toUse('App\Modules\Fulfillment');

arch('fulfillment does not know payment')
    ->expect('App\Modules\Fulfillment')
    ->not->toUse('App\Modules\Payment');

// Shared kernel: infrastructure only, no business module.
foreach (array_diff(MODULES, ['Shared']) as $module) {
    arch("the shared kernel does not depend on {$module}")
        ->expect('App\Modules\Shared')
        ->not->toUse("App\\Modules\\{$module}");
}

it('resolves the inventory contracts to the stock repository', function (string $contract) {
    expect(app($contract))->toBeInstanceOf(StockRepository::class);
})->with([StockInitializer::class, StockReservation::class]);

it('keeps every module named by these rules', function (string $module) {
    expect(glob(app_path("Modules/{$module}/*")))->not->toBeEmpty();
})->with(MODULES);
