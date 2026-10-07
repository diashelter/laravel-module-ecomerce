<?php

use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\Hash;

it('shows the account summary', function () {
    $user = customer();
    Order::factory()->count(6)->for($user, 'customer')->create();

    $this->actingAs($user)->getJson('/api/account')
        ->assertOk()
        ->assertJsonPath('data.customer.email', $user->email)
        ->assertJsonPath('data.orders_count', 6)
        ->assertJsonCount(5, 'data.recent_orders')
        ->assertJsonStructure(['data' => ['last_order' => ['id', 'status']]]);
});

it('updates name and email', function () {
    $user = customer();

    $this->actingAs($user)->putJson('/api/account/profile', ['name' => 'Novo Nome', 'email' => 'novo@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Novo Nome');

    expect($user->fresh()->email)->toBe('novo@example.com');
});

it('changes the password only with the current password', function () {
    $user = customer();
    $payload = ['name' => $user->name, 'email' => $user->email, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'];

    $this->actingAs($user)->putJson('/api/account/profile', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->actingAs($user)->putJson('/api/account/profile', [...$payload, 'current_password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    $this->actingAs($user)->putJson('/api/account/profile', [...$payload, 'current_password' => 'password'])->assertOk();

    expect(Hash::check('newpass123', $user->fresh()->password))->toBeTrue();
});

it('does not allow taking another user email', function () {
    customer(['email' => 'taken@example.com']);

    $this->actingAs(customer())->putJson('/api/account/profile', ['name' => 'X', 'email' => 'taken@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('lets a customer take an e-mail used by a staff member', function () {
    admin(['email' => 'equipe@example.com']);
    $account = customer();

    $this->actingAs($account)->putJson('/api/account/profile', ['name' => 'Ana', 'email' => 'equipe@example.com'])->assertOk();

    expect($account->fresh()->email)->toBe('equipe@example.com');
});

it('rejects a profile e-mail already used by another customer', function () {
    customer(['email' => 'taken@example.com']);
    $account = customer(['email' => 'mine@example.com']);

    $this->actingAs($account)->putJson('/api/account/profile', ['name' => 'Ana', 'email' => 'taken@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($account->fresh()->email)->toBe('mine@example.com');
});

it('returns the account summary under the customer key without a role', function () {
    $account = customer();

    $response = $this->actingAs($account)->getJson('/api/account')->assertOk();

    expect(array_keys($response->json('data')))->toEqualCanonicalizing(['customer', 'last_order', 'orders_count', 'recent_orders'])
        ->and(array_keys($response->json('data.customer')))->toEqualCanonicalizing(['created_at', 'email', 'id', 'name']);
});
