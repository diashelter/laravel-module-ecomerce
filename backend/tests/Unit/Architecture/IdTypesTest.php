<?php

use Modules\Catalog\Contracts\ProductOrderHistory;
use Modules\Fulfillment\Core\Jobs\DeliverOrder;
use Modules\Fulfillment\Events\DeliveryScheduled;
use Modules\Fulfillment\Events\OrderDelivered;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Inventory\Contracts\StockInitializer;
use Modules\Inventory\Contracts\StockReservation;
use Modules\Ordering\Contracts\CustomerOrderHistory;
use Modules\Ordering\Contracts\DeliveryAddressBook;
use Modules\Ordering\Contracts\PayableOrders;
use Modules\Ordering\Core\ValueObjects\OrderForPayment;
use Modules\Ordering\Events\OrderPaid;
use Modules\Ordering\Events\OrderPlaced;
use Modules\Payment\Events\PaymentApproved;
use Tests\Unit\Architecture\Support\ModuleLayout;

function isIdName(string $name): bool
{
    return $name === 'id' || str_ends_with($name, 'Id');
}

it('types every id of the contracts as a string', function (string $contract, string $method) {
    $ids = array_filter(
        (new ReflectionMethod($contract, $method))->getParameters(),
        fn (ReflectionParameter $parameter) => isIdName($parameter->getName()),
    );

    expect($ids)->not->toBeEmpty("{$contract}::{$method} has no id parameter");

    foreach ($ids as $parameter) {
        $type = $parameter->getType();

        expect($type instanceof ReflectionNamedType && $type->getName() === 'string' && ! $type->allowsNull())
            ->toBeTrue("{$contract}::{$method}() \${$parameter->getName()} is not a string");
    }
})->with([
    [ProductOrderHistory::class, 'hasBeenOrdered'],
    [CustomerAccounts::class, 'updateProfile'],
    [CustomerAccounts::class, 'findProfile'],
    [StockInitializer::class, 'createForProduct'],
    [StockReservation::class, 'decrement'],
    [CustomerOrderHistory::class, 'countForCustomer'],
    [CustomerOrderHistory::class, 'recentForCustomer'],
    [DeliveryAddressBook::class, 'find'],
    [PayableOrders::class, 'findForPayment'],
]);

it('declares the owner in the payable orders contract', function () {
    $method = new ReflectionMethod(PayableOrders::class, 'findForPayment');
    $parameters = array_map(fn (ReflectionParameter $parameter) => [$parameter->getName(), (string) $parameter->getType()], $method->getParameters());

    expect($parameters)->toBe([['customerId', 'string'], ['orderId', 'string']])
        ->and((string) $method->getReturnType())->toBe('?'.OrderForPayment::class);
});

it('carries the order id as a string in the order events and the delivery job', function (string $class) {
    $type = (new ReflectionProperty($class, 'orderId'))->getType();

    expect((new ReflectionProperty($class, 'orderId'))->isPublic())->toBeTrue()
        ->and($type instanceof ReflectionNamedType && $type->getName() === 'string' && ! $type->allowsNull())->toBeTrue();
})->with([
    OrderPlaced::class,
    OrderPaid::class,
    PaymentApproved::class,
    DeliveryScheduled::class,
    OrderDelivered::class,
    DeliverOrder::class,
]);

/** @return list<string> the names in a native type, with unions flattened */
function idTypeNames(?ReflectionType $type): array
{
    if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
        return array_merge(...array_map('idTypeNames', $type->getTypes()));
    }

    return $type instanceof ReflectionNamedType ? [$type->getName()] : [];
}

it('declares no integer id in the modules', function () {
    $root = realpath(base_path('app-modules'));
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $read = 0;
    $violations = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        if (! str_contains($file->getPathname(), '/src/')) {
            continue;
        }

        $class = ModuleLayout::classOf($file->getPathname());

        if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
            continue;
        }

        $read++;
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                if (isIdName($parameter->getName()) && in_array('int', idTypeNames($parameter->getType()), true)) {
                    $violations[] = "{$class}::{$method->getName()}() \${$parameter->getName()}";
                }
            }
        }

        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $class && isIdName($property->getName()) && in_array('int', idTypeNames($property->getType()), true)) {
                $violations[] = "{$class}::\${$property->getName()}";
            }
        }
    }

    expect($read)->toBeGreaterThanOrEqual(200)
        ->and($violations)->toBe([]);
});
