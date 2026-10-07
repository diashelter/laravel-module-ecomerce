<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\User;

beforeEach(fn () => $this->withHeader('Origin', 'http://localhost'));

function staffPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Novo Membro',
        'email' => 'novo@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'support',
    ], $overrides);
}

it('lists only staff members to the admin', function () {
    $oldest = admin(['created_at' => now()->subDays(2)]);
    $middle = support(['created_at' => now()->subDay()]);
    $newest = support(['created_at' => now()]);
    customer(['email' => 'cliente@example.com']);
    customer();

    $response = $this->actingAs($oldest)->getJson('/api/admin/users')->assertOk();

    expect($response->json('meta.total'))->toBe(3)
        ->and($response->json('meta.per_page'))->toBe(15)
        ->and(collect($response->json('data'))->pluck('id')->all())->toBe([$newest->id, $middle->id, $oldest->id])
        ->and(collect($response->json('data'))->every(fn (array $item) => isset($item['role'], $item['role_label'])))->toBeTrue()
        ->and(collect($response->json('data'))->pluck('email'))->not->toContain('cliente@example.com');
});

it('shows a staff member to the admin', function () {
    $member = support(['name' => 'Beatriz', 'email' => 'bia@example.com']);

    $response = $this->actingAs(admin())->getJson("/api/admin/users/{$member->id}")->assertOk();

    expect(array_keys($response->json('data')))->toEqualCanonicalizing(['created_at', 'email', 'id', 'name', 'role', 'role_label'])
        ->and($response->json('data.id'))->toBe($member->id)
        ->and($response->json('data.name'))->toBe('Beatriz')
        ->and($response->json('data.email'))->toBe('bia@example.com')
        ->and($response->json('data.role'))->toBe('support')
        ->and($response->json('data.role_label'))->toBe('Suporte');
});

it('creates a support member from the admin', function () {
    $this->actingAs(admin())->postJson('/api/admin/users', staffPayload())
        ->assertCreated()
        ->assertJsonPath('data.role', 'support');

    expect(User::query()->where('email', 'novo@example.com')->sole()->role)->toBe(UserRole::Support);
});

it('rejects a staff role other than admin or support', function (?string $role) {
    $this->actingAs(admin());
    $payload = staffPayload();
    $role === null ? $payload['role'] = null : $payload['role'] = $role;
    if ($role === null) {
        unset($payload['role']);
    }

    $this->postJson('/api/admin/users', $payload)->assertUnprocessable()->assertJsonValidationErrors('role');

    expect(User::query()->where('email', 'novo@example.com')->exists())->toBeFalse();
})->with(['absent' => [null], 'customer' => ['customer'], 'superadmin' => ['superadmin']]);

it('rejects a staff e-mail already in use', function (string $method) {
    $this->actingAs(admin());
    support(['email' => 'taken@example.com']);
    $edited = support(['name' => 'Original', 'email' => 'original@example.com']);
    $members = User::query()->count();

    $response = $method === 'POST'
        ? $this->postJson('/api/admin/users', staffPayload(['email' => 'taken@example.com']))
        : $this->putJson("/api/admin/users/{$edited->id}", staffPayload(['email' => 'taken@example.com']));

    $response->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(User::query()->count())->toBe($members)
        ->and($edited->fresh()->email)->toBe('original@example.com')
        ->and($edited->fresh()->name)->toBe('Original');
})->with(['POST', 'PUT']);

it('creates a staff member with an e-mail used by a customer', function () {
    customer(['email' => 'cli@example.com']);

    $this->actingAs(admin())->postJson('/api/admin/users', staffPayload(['email' => 'cli@example.com']))->assertCreated();
});

it('promotes a support member who can then delete', function () {
    $boss = admin();
    $member = support(['email' => 'equipe@example.com']);
    $product = productWithStock(2);

    $this->actingAs($boss)->putJson("/api/admin/users/{$member->id}", staffPayload(['email' => 'equipe@example.com', 'role' => 'admin', 'password' => null, 'password_confirmation' => null]))->assertOk();

    // The member signs in and acts on a following request, read again from the session.
    app('auth')->forgetGuards();
    $this->postJson('/api/admin/auth/login', ['email' => 'equipe@example.com', 'password' => 'password'])->assertOk();
    $session = app('session')->getId();

    app('auth')->forgetGuards();
    $this->withCookie(config('session.cookie'), $session)->deleteJson("/api/admin/products/{$product->id}")->assertNoContent();

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});

it('refuses changing the own role', function () {
    $boss = admin();

    $response = $this->actingAs($boss)->putJson("/api/admin/users/{$boss->id}", staffPayload(['email' => $boss->email, 'role' => 'support']))->assertStatus(409);

    expect($response->json('code'))->toBe('BUSINESS_RULE_VIOLATION')
        ->and($response->json('message'))->toBe('Você não pode alterar o próprio papel.')
        ->and($boss->fresh()->role)->toBe(UserRole::Admin);
});

it('lets the admin edit the own name keeping the role', function () {
    $boss = admin();

    $this->actingAs($boss)->putJson("/api/admin/users/{$boss->id}", staffPayload(['name' => 'Outro Nome', 'email' => $boss->email, 'role' => 'admin']))->assertOk();

    expect($boss->fresh()->name)->toBe('Outro Nome');
});

it('removes another staff member and ends their access', function () {
    $boss = admin();
    $member = support(['email' => 'equipe@example.com']);

    $this->postJson('/api/admin/auth/login', ['email' => 'equipe@example.com', 'password' => 'password'])->assertOk();
    $memberSession = app('session')->getId();

    app('auth')->forgetGuards();
    $this->actingAs($boss)->deleteJson("/api/admin/users/{$member->id}")->assertNoContent();

    expect(User::query()->whereKey($member->id)->exists())->toBeFalse();

    app('auth')->forgetGuards();
    $this->withCookie(config('session.cookie'), $memberSession)->getJson('/api/admin/auth/me')->assertUnauthorized();
});

it('refuses removing the own staff account', function () {
    $boss = admin();

    $response = $this->actingAs($boss)->deleteJson("/api/admin/users/{$boss->id}")->assertStatus(409);

    expect($response->json('code'))->toBe('BUSINESS_RULE_VIOLATION')
        ->and($response->json('message'))->toBe('Você não pode remover a própria conta.')
        ->and(User::query()->whereKey($boss->id)->exists())->toBeTrue();
});

it('forbids the support role from managing staff', function (string $method, string $uri) {
    $actor = support();
    $target = admin(['name' => 'Alvo']);
    $before = User::query()->orderBy('id')->get(['id', 'name', 'email', 'role'])->toArray();

    $this->actingAs($actor)
        ->json($method, str_replace('{user}', (string) $target->id, $uri), staffPayload(['email' => 'novo@example.com']))
        ->assertForbidden();

    expect(User::query()->orderBy('id')->get(['id', 'name', 'email', 'role'])->toArray())->toBe($before);
})->with([
    ['GET', '/api/admin/users'],
    ['POST', '/api/admin/users'],
    ['GET', '/api/admin/users/{user}'],
    ['PUT', '/api/admin/users/{user}'],
    ['DELETE', '/api/admin/users/{user}'],
]);
