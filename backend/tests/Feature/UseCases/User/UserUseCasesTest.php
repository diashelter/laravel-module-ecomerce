<?php

use Illuminate\Support\Facades\Hash;
use Modules\Customers\Core\UseCases\CreateCustomerUseCase;
use Modules\Customers\Core\UseCases\ListCustomersUseCase;
use Modules\Customers\Core\UseCases\ShowCustomerUseCase;
use Modules\Customers\Core\UseCases\UpdateCustomerUseCase;
use Modules\Customers\Core\UseCases\UpdateOwnProfileUseCase;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Core\DTOs\CreateUserDTO;
use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Identity\Core\UseCases\RegisterCustomerUseCase;
use Modules\Identity\Core\ValueObjects\CustomerProfile;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Core\ValueObjects\Password;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Ordering\Core\ValueObjects\OrderSummaries;
use Modules\Ordering\Persistence\Models\Order;

it('registers a customer account', function () {
    $account = app(RegisterCustomerUseCase::class)->execute(new CreateUserDTO('Maria', new Email('maria@example.com'), new Password('secret-password')));

    expect($account->fresh()->getTable())->toBe('customers')
        ->and(Hash::check('secret-password', $account->fresh()->password))->toBeTrue();

    // Other modules register through the identity contract and get the profile, never the model.
    $profile = app(CustomerAccounts::class)->register(new CreateUserDTO('Ana', new Email('Ana.Souza@Example.com'), new Password('secret-password')));

    expect($profile)->toBeInstanceOf(CustomerProfile::class)
        ->and($profile->email)->toBe('ana.souza@example.com')
        ->and(property_exists($profile, 'password'))->toBeFalse()
        ->and(Hash::check('secret-password', CustomerAccount::query()->findOrFail($profile->id)->password))->toBeTrue();
});

it('finds no profile for an unknown customer', function () {
    $account = customer(['name' => 'Maria']);

    expect(app(CustomerAccounts::class)->findProfile(unknownId()))->toBeNull()
        ->and(app(CustomerAccounts::class)->findProfile($account->id))
        ->toEqual(new CustomerProfile($account->id, 'Maria', $account->email, $account->created_at->toImmutable()));
});

it('creates customers with an empty purchase history', function () {
    $summary = app(CreateCustomerUseCase::class)->execute(new CreateUserDTO('João', new Email('joao@example.com'), new Password('secret-password')));

    expect(CustomerAccount::query()->findOrFail($summary->account->id)->email)->toBe('joao@example.com')
        ->and($summary->ordersCount)->toBe(0);
});

it('updates the own profile keeping the password when none is sent', function () {
    $user = customer();

    app(UpdateOwnProfileUseCase::class)->execute($user->id, new UpdateUserProfileDTO('Novo Nome', new Email('novo@example.com')));

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('Novo Nome')
        ->and($fresh->email)->toBe('novo@example.com')
        ->and(Hash::check(UserFactory::DEFAULT_PASSWORD, $fresh->password))->toBeTrue();
});

it('changes the customer password when a new one is sent', function () {
    $user = customer();

    Order::factory()->count(2)->for($user, 'customer')->create();

    $summary = app(UpdateCustomerUseCase::class)->execute($user->id, new UpdateUserProfileDTO($user->name, new Email($user->email), new Password('new-secret-password')));

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue()
        ->and($summary->ordersCount)->toBe(2);
});

it('lists accounts with the order count of each one', function () {
    $buyer = customer(['created_at' => now()]);
    $withoutOrders = customer(['created_at' => now()->subDay()]);
    Order::factory()->count(3)->for($buyer, 'customer')->create();

    $summaries = collect(app(ListCustomersUseCase::class)->execute(15)->items())->keyBy(fn ($summary) => $summary->account->id);

    expect($summaries->keys()->all())->toBe([$buyer->id, $withoutOrders->id])
        ->and($summaries[$buyer->id]->account)->toBeInstanceOf(CustomerProfile::class)
        ->and($summaries[$buyer->id]->ordersCount)->toBe(3)
        ->and($summaries[$withoutOrders->id]->ordersCount)->toBe(0)
        ->and($summaries[$buyer->id]->recentOrders)->toBeNull();
});

it('shows an account with its order count and only the most recent orders', function () {
    $user = customer();
    $orders = Order::factory()->count(12)->for($user, 'customer')->create(['created_at' => now()]);

    $summary = app(ShowCustomerUseCase::class)->execute($user->id, 10);

    expect($summary->ordersCount)->toBe(12)
        ->and($summary->recentOrders)->toBeInstanceOf(OrderSummaries::class)
        ->and($summary->recentOrders)->toHaveCount(10)
        ->and($summary->recentOrders->first()->id)->toBe($orders->last()->id);
});
