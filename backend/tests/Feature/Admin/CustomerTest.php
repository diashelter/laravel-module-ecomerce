<?php

use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Facades\DB;

dataset('staff roles', [
    'admin' => [fn () => admin()],
    'support' => [fn () => support()],
]);

it('lists only customers with their order count', function (Closure $member) {
    $this->actingAs($member());
    $oldest = customer(['created_at' => now()->subDays(2)]);
    $middle = customer(['created_at' => now()->subDay()]);
    $newest = customer(['created_at' => now()]);
    Order::factory()->count(2)->for($newest, 'customer')->create();
    support(['email' => 'equipe@example.com']);

    $response = $this->getJson('/api/admin/customers')->assertOk();

    expect($response->json('meta.total'))->toBe(3)
        ->and($response->json('meta.per_page'))->toBe(15)
        ->and(collect($response->json('data'))->pluck('id')->all())->toBe([$newest->id, $middle->id, $oldest->id])
        ->and(collect($response->json('data'))->pluck('email'))->not->toContain('equipe@example.com')
        ->and($response->json('data.0.orders_count'))->toBe(2)
        ->and($response->json('data.1.orders_count'))->toBe(0);
})->with('staff roles');

it('reads the order counts of a customers page in one query', function () {
    $this->actingAs(admin());
    CustomerAccount::factory()->count(14)->create()
        ->each(fn (CustomerAccount $account) => Order::factory()->for($account, 'customer')->create());

    DB::enableQueryLog();
    $this->getJson('/api/admin/customers')->assertOk()->assertJsonCount(14, 'data');
    $orderQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "orders"'));
    DB::disableQueryLog();

    expect($orderQueries)->toHaveCount(1);
});

it('creates a customer from the admin', function (Closure $member) {
    $this->actingAs($member());
    $users = User::query()->count();

    $this->postJson('/api/admin/customers', [
        'name' => 'Novo Cliente',
        'email' => 'novo@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'admin',
    ])->assertCreated()->assertJsonMissingPath('data.role');

    expect(CustomerAccount::query()->where('email', 'novo@example.com')->count())->toBe(1)
        ->and(User::query()->count())->toBe($users);
})->with('staff roles');

it('creates a customer with an e-mail used by a staff member', function () {
    $this->actingAs(admin());
    support(['email' => 'equipe@example.com']);

    $this->postJson('/api/admin/customers', [
        'name' => 'Cliente',
        'email' => 'equipe@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertCreated();
});

it('rejects a customer e-mail already in use', function (string $method) {
    $this->actingAs(admin());
    customer(['email' => 'taken@example.com']);
    $edited = customer(['name' => 'Original', 'email' => 'original@example.com']);
    $customers = CustomerAccount::query()->count();

    $payload = ['name' => 'Outro', 'email' => 'taken@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123'];

    $response = $method === 'POST'
        ? $this->postJson('/api/admin/customers', $payload)
        : $this->putJson("/api/admin/customers/{$edited->id}", $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(CustomerAccount::query()->count())->toBe($customers)
        ->and($edited->fresh()->email)->toBe('original@example.com')
        ->and($edited->fresh()->name)->toBe('Original');
})->with(['POST', 'PUT']);

it('shows a customer with the ten most recent orders', function (Closure $member) {
    $this->actingAs($member());
    $account = customer();
    $orders = collect(range(1, 12))->map(fn (int $day) => Order::factory()->for($account, 'customer')->create(['created_at' => now()->subDays(13 - $day)]));

    $response = $this->getJson("/api/admin/customers/{$account->id}")->assertOk();

    expect($response->json('data.orders_count'))->toBe(12)
        ->and(collect($response->json('data.orders'))->pluck('id')->all())->toBe($orders->reverse()->take(10)->pluck('id')->values()->all())
        ->and($response->json('data.email'))->toBe($account->email);
})->with('staff roles');

it('updates a customer from the admin', function (Closure $member) {
    $this->actingAs($member());
    $account = customer();
    Order::factory()->count(2)->for($account, 'customer')->create();

    $this->putJson("/api/admin/customers/{$account->id}", ['name' => 'Novo Nome', 'email' => $account->email])
        ->assertOk()
        ->assertJsonPath('data.name', 'Novo Nome')
        ->assertJsonPath('data.orders_count', 2);

    expect($account->fresh()->name)->toBe('Novo Nome');
})->with('staff roles');

it('does not delete customers', function () {
    $this->actingAs(admin());
    $account = customer();

    $this->deleteJson("/api/admin/customers/{$account->id}")->assertStatus(405);

    expect(CustomerAccount::query()->whereKey($account->id)->exists())->toBeTrue();
});

it('counts only customers on the dashboard', function () {
    $this->actingAs(admin());
    CustomerAccount::factory()->count(4)->create();
    support();

    $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('data.cards.total_customers', 4);
});
