<?php

use App\Modules\Customers\UseCases\CreateCustomerUseCase;
use App\Modules\Customers\UseCases\ListCustomersUseCase;
use App\Modules\Customers\UseCases\ShowCustomerUseCase;
use App\Modules\Customers\UseCases\UpdateCustomerUseCase;
use App\Modules\Customers\UseCases\UpdateOwnProfileUseCase;
use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\UseCases\RegisterCustomerUseCase;
use App\Modules\Ordering\Models\Order;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;

it('registers users as customers', function () {
    $user = app(RegisterCustomerUseCase::class)->execute(new CreateUserDTO('Maria', 'maria@example.com', 'secret-password'));

    expect($user->fresh()->role)->toBe(UserRole::Customer)
        ->and(Hash::check('secret-password', $user->fresh()->password))->toBeTrue();
});

it('creates customers with an empty purchase history', function () {
    $summary = app(CreateCustomerUseCase::class)->execute(new CreateUserDTO('João', 'joao@example.com', 'secret-password'));

    expect($summary->account->fresh()->role)->toBe(UserRole::Customer)
        ->and($summary->ordersCount)->toBe(0);
});

it('updates the own profile keeping the password when none is sent', function () {
    $user = customer();

    app(UpdateOwnProfileUseCase::class)->execute($user, new UpdateUserProfileDTO('Novo Nome', 'novo@example.com'));

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('Novo Nome')
        ->and($fresh->email)->toBe('novo@example.com')
        ->and(Hash::check(UserFactory::DEFAULT_PASSWORD, $fresh->password))->toBeTrue();
});

it('changes the customer password when a new one is sent', function () {
    $user = customer();

    Order::factory()->count(2)->for($user, 'customer')->create();

    $summary = app(UpdateCustomerUseCase::class)->execute($user, new UpdateUserProfileDTO($user->name, $user->email, 'new-secret-password'));

    expect(Hash::check('new-secret-password', $user->fresh()->password))->toBeTrue()
        ->and($summary->ordersCount)->toBe(2);
});

it('lists accounts with the order count of each one', function () {
    $buyer = customer(['created_at' => now()]);
    $withoutOrders = customer(['created_at' => now()->subDay()]);
    Order::factory()->count(3)->for($buyer, 'customer')->create();

    $summaries = collect(app(ListCustomersUseCase::class)->execute(15)->items())->keyBy(fn ($summary) => $summary->account->id);

    expect($summaries[$buyer->id]->ordersCount)->toBe(3)
        ->and($summaries[$withoutOrders->id]->ordersCount)->toBe(0)
        ->and($summaries[$buyer->id]->recentOrders)->toBeNull();
});

it('shows an account with its order count and only the most recent orders', function () {
    $user = customer();
    $orders = Order::factory()->count(12)->for($user, 'customer')->create(['created_at' => now()]);

    $summary = app(ShowCustomerUseCase::class)->execute($user, 10);

    expect($summary->ordersCount)->toBe(12)
        ->and($summary->recentOrders)->toHaveCount(10)
        ->and($summary->recentOrders->first()->id)->toBe($orders->last()->id)
        ->and($summary->recentOrders->first()->items_count)->toBe(0);
});
