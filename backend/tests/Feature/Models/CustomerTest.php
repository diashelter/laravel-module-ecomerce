<?php

use App\Modules\Ordering\Models\Customer;
use App\Modules\Ordering\Models\Order;

it('is the buyer of an order, read from the account', function () {
    $user = customer(['name' => 'Maria', 'email' => 'maria@example.com']);
    $order = Order::factory()->for($user, 'customer')->create();

    $buyer = $order->customer;

    expect($buyer)->toBeInstanceOf(Customer::class)
        ->and($buyer->id)->toBe($user->id)
        ->and($buyer->toArray())->toBe(['id' => $user->id, 'name' => 'Maria', 'email' => 'maria@example.com']);
});

it('never writes to the accounts table', function (Closure $write) {
    $user = customer(['name' => 'Maria']);
    $buyer = Customer::query()->findOrFail($user->id);

    expect(fn () => $write($buyer))->toThrow(LogicException::class)
        ->and($user->fresh()?->name)->toBe('Maria');
})->with([
    'save' => [fn (Customer $buyer) => $buyer->forceFill(['name' => 'Hacked'])->save()],
    'delete' => [fn (Customer $buyer) => $buyer->delete()],
]);
