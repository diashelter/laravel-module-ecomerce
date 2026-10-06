<?php

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\User;
use App\Modules\Ordering\Models\Order;

beforeEach(fn () => $this->actingAs(admin()));

it('lists users with their order count', function () {
    $customer = customer();
    Order::factory()->count(2)->for($customer, 'customer')->create();

    $users = collect($this->getJson('/api/admin/users')->assertOk()->json('data'))->keyBy('id');

    expect($users[$customer->id]['orders_count'])->toBe(2)
        ->and($users[$customer->id]['role'])->toBe('customer');
});

it('always creates customers, never admins', function () {
    $this->postJson('/api/admin/users', [
        'name' => 'Novo Cliente',
        'email' => 'novo@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'admin',
    ])->assertCreated()->assertJsonPath('data.role', 'customer');

    expect(User::query()->where('email', 'novo@example.com')->first()->role)->toBe(UserRole::Customer);
});

it('shows and updates a customer', function () {
    $customer = customer();
    Order::factory()->count(2)->for($customer, 'customer')->create();

    $this->getJson("/api/admin/users/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.email', $customer->email)
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonPath('data.orders_count', 2)
        ->assertJsonCount(2, 'data.orders');

    $this->putJson("/api/admin/users/{$customer->id}", ['name' => 'Editado', 'email' => 'editado@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Editado')
        ->assertJsonPath('data.orders_count', 2);
});

it('lists administrators too, without orders', function () {
    $otherAdmin = admin();

    $users = collect($this->getJson('/api/admin/users')->assertOk()->json('data'))->keyBy('id');

    expect($users[$otherAdmin->id]['role'])->toBe('admin')
        ->and($users[$otherAdmin->id]['orders_count'])->toBe(0)
        ->and($users[$otherAdmin->id])->not->toHaveKey('orders');
});

it('does not edit administrators through the customers screen', function () {
    $otherAdmin = admin();

    $this->putJson("/api/admin/users/{$otherAdmin->id}", ['name' => 'Hacked', 'email' => $otherAdmin->email])
        ->assertForbidden();
});
