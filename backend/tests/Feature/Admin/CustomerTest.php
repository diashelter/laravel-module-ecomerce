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

    $response = $this->postJson('/api/admin/customers', [
        'name' => 'Novo Cliente',
        'email' => 'NOVO.Cliente@Example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'admin',
    ])->assertCreated()
        ->assertJsonMissingPath('data.role')
        ->assertJsonPath('data.name', 'Novo Cliente')
        ->assertJsonPath('data.email', 'novo.cliente@example.com')
        ->assertJsonPath('data.orders_count', 0);

    $account = CustomerAccount::query()->where('email', 'novo.cliente@example.com')->sole();
    expect($response->json('data.id'))->toBe($account->id)
        ->and($response->json('data.created_at'))->toBe($account->created_at->toIso8601String())
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

it('renders the account fields on every admin customer route', function () {
    $this->actingAs(admin());
    $account = customer();
    $keys = ['id', 'name', 'email', 'created_at', 'orders_count'];
    $createdAt = $account->created_at->toIso8601String();

    $listed = $this->getJson('/api/admin/customers')->assertOk()->json('data.0');
    $shown = $this->getJson("/api/admin/customers/{$account->id}")->assertOk()->json('data');
    $updated = $this->putJson("/api/admin/customers/{$account->id}", ['name' => 'Novo Nome', 'email' => $account->email])->assertOk()->json('data');
    $created = $this->postJson('/api/admin/customers', [
        'name' => 'Novo Cliente',
        'email' => 'novo@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ])->assertCreated()->json('data');

    expect(array_keys($listed))->toBe($keys)
        ->and($listed['created_at'])->toBe($createdAt)
        ->and(array_keys($shown))->toBe([...$keys, 'orders'])
        ->and($shown['created_at'])->toBe($createdAt)
        ->and(array_keys($updated))->toBe($keys)
        ->and($updated['created_at'])->toBe($createdAt)
        ->and(array_keys($created))->toBe($keys)
        ->and($created['created_at'])->toBe(CustomerAccount::query()->where('email', 'novo@example.com')->sole()->created_at->toIso8601String());
});

it('answers 404 to an unknown customer in the admin', function () {
    $this->actingAs(admin());

    $this->getJson('/api/admin/customers/999999')->assertNotFound();
    $this->putJson('/api/admin/customers/999999', ['name' => 'Novo Nome', 'email' => 'novo@example.com'])->assertNotFound();
});

it('pages customers fifteen at a time, newest first', function () {
    $this->actingAs(admin());
    $accounts = collect(range(1, 16))->map(fn (int $minutes) => customer(['created_at' => now()->subMinutes($minutes)]));

    $response = $this->getJson('/api/admin/customers')->assertOk();

    expect($response->json('meta.total'))->toBe(16)
        ->and(collect($response->json('data'))->pluck('id')->all())->toBe($accounts->take(15)->pluck('id')->all())
        ->and(collect($response->json('data'))->pluck('orders_count')->unique()->all())->toBe([0]);
});

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
