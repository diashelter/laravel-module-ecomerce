<?php

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\Hash;

it('shows the account summary', function () {
    $user = customer();
    $orders = collect(range(1, 6))->map(fn (int $day) => Order::factory()->for($user, 'customer')->create(['created_at' => now()->subDays(7 - $day)]));

    $response = $this->actingAs($user)->getJson('/api/account')
        ->assertOk()
        ->assertJsonPath('data.customer.email', $user->email)
        ->assertJsonPath('data.orders_count', 6)
        ->assertJsonCount(5, 'data.recent_orders')
        ->assertJsonPath('data.last_order.id', $orders->last()->id);

    expect(collect($response->json('data.recent_orders'))->pluck('id')->all())
        ->toBe($orders->reverse()->take(5)->pluck('id')->values()->all());
});

it('shows an empty account summary for a customer without orders', function () {
    Order::factory()->create();

    $this->actingAs(customer())->getJson('/api/account')
        ->assertOk()
        ->assertJsonPath('data.orders_count', 0)
        ->assertJsonPath('data.last_order', null)
        ->assertJsonPath('data.recent_orders', []);
});

it('renders the recent orders as summaries', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::AwaitingPayment)->create(['total_cents' => 15990]);
    $summary = [
        'id' => $order->id,
        'status' => 'awaiting_payment',
        'status_label' => OrderStatus::AwaitingPayment->label(),
        'total_cents' => 15990,
        'created_at' => $order->created_at->toIso8601String(),
    ];

    $account = $this->actingAs($user)->getJson('/api/account')->assertOk();
    $admin = $this->actingAs(admin())->getJson("/api/admin/customers/{$user->id}")->assertOk();

    expect($account->json('data.last_order'))->toBe($summary)
        ->and($account->json('data.recent_orders'))->toBe([$summary])
        ->and($admin->json('data.orders'))->toBe([$summary]);
});

it('updates name and email', function () {
    $user = customer();

    $response = $this->actingAs($user)->putJson('/api/account/profile', ['name' => 'Novo Nome', 'email' => 'novo@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Novo Nome')
        ->assertJsonPath('data.email', 'novo@example.com')
        ->assertJsonPath('message', 'Dados atualizados com sucesso.');

    expect(array_keys($response->json('data')))->toEqualCanonicalizing(['id', 'name', 'email', 'created_at'])
        ->and($user->fresh()->email)->toBe('novo@example.com');
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
        ->and(array_keys($response->json('data.customer')))->toEqualCanonicalizing(['created_at', 'email', 'id', 'name'])
        ->and($response->json('data.customer.created_at'))->toBe($account->created_at->toIso8601String());
});
