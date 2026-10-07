<?php

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Inventory\Contracts\StockInitializer;
use App\Modules\Inventory\Contracts\StockReservation;
use App\Modules\Inventory\Repositories\StockRepository;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Gateways\FakePaymentGateway;

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

// Catalog: it does not read order data; it asks through its own ProductOrderHistory contract,
// implemented by the ordering module.
foreach (['Models', 'Repositories'] as $layer) {
    arch("the catalog does not use the ordering {$layer}")
        ->expect('App\Modules\Catalog')
        ->not->toUse("App\\Modules\\Ordering\\{$layer}");
}

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

// Payment charges through its own PaymentGateway port: only the module's service provider
// names the adapter, so swapping the gateway is a new class and one binding.
foreach (['UseCases', 'Services', 'Http'] as $layer) {
    arch("payment {$layer} reaches the payment gateway only through its contract")
        ->expect("App\\Modules\\Payment\\{$layer}")
        ->not->toUse(FakePaymentGateway::class);
}

arch('the payment contracts are interfaces')
    ->expect('App\Modules\Payment\Contracts')
    ->toBeInterfaces();

// Shared kernel: infrastructure only, no business module.
foreach (array_diff(MODULES, ['Shared']) as $module) {
    arch("the shared kernel does not depend on {$module}")
        ->expect('App\Modules\Shared')
        ->not->toUse("App\\Modules\\{$module}");
}

it('resolves the catalog order history to the order repository', function () {
    expect(app(ProductOrderHistory::class))->toBeInstanceOf(OrderRepository::class);
});

it('resolves the inventory contracts to the stock repository', function (string $contract) {
    expect(app($contract))->toBeInstanceOf(StockRepository::class);
})->with([StockInitializer::class, StockReservation::class]);

it('resolves the payment gateway to the fake gateway', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakePaymentGateway::class);
});

it('keeps every module named by these rules', function (string $module) {
    expect(glob(app_path("Modules/{$module}/*")))->not->toBeEmpty();
})->with(MODULES);

// Value objects: immutable by construction, and the account rules live in them only.
arch('identity value objects are final and readonly')
    ->expect('App\Modules\Identity\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

foreach ([
    'App\Modules\Identity\Http\Requests\RegisterRequest',
    'App\Modules\Identity\Http\Requests\LoginRequest',
    'App\Modules\Customers\Http\Requests\Admin\StoreCustomerRequest',
    'App\Modules\Customers\Http\Requests\Admin\UpdateCustomerRequest',
    'App\Modules\Customers\Http\Requests\UpdateProfileRequest',
] as $request) {
    arch("{$request} does not write the password policy itself")
        ->expect($request)
        ->not->toUse('Illuminate\Validation\Rules\Password');
}
