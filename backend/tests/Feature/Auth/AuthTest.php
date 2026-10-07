<?php

use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Models\User;

// Sanctum only starts a session for "stateful" requests coming from the SPA domain.
beforeEach(fn () => $this->withHeader('Origin', 'http://localhost'));

const CUSTOMER_KEYS = ['created_at', 'email', 'id', 'name'];

function registrationPayload(string $email): array
{
    return ['name' => 'Maria', 'email' => $email, 'password' => 'secret123', 'password_confirmation' => 'secret123'];
}

it('registers a customer account in the customers table', function () {
    $response = $this->postJson('/api/auth/register', [...registrationPayload('maria@example.com'), 'role' => 'admin'])->assertCreated();

    expect(array_keys($response->json('data')))->toEqualCanonicalizing(CUSTOMER_KEYS)
        ->and(CustomerAccount::query()->where('email', 'maria@example.com')->count())->toBe(1)
        ->and(User::query()->count())->toBe(0);
    $this->assertAuthenticatedAs(CustomerAccount::query()->sole(), 'customer');
    $this->assertGuest('staff');
});

it('registers a customer with an e-mail used by a staff member', function () {
    admin(['email' => 'ana@example.com']);

    $this->postJson('/api/auth/register', registrationPayload('ana@example.com'))->assertCreated();

    expect(CustomerAccount::query()->where('email', 'ana@example.com')->count())->toBe(1)
        ->and(User::query()->where('email', 'ana@example.com')->count())->toBe(1);
});

it('rejects registering an e-mail already used by a customer', function () {
    customer(['email' => 'ana@example.com']);

    $this->postJson('/api/auth/register', registrationPayload('ana@example.com'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(CustomerAccount::query()->where('email', 'ana@example.com')->count())->toBe(1);
});

it('validates registration data', function () {
    customer(['email' => 'taken@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => '',
        'email' => 'taken@example.com',
        'password' => '123',
        'password_confirmation' => '456',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('logs a customer in with the customer data only', function () {
    $account = customer(['email' => 'john@example.com']);

    $login = $this->postJson('/api/auth/login', ['email' => 'john@example.com', 'password' => 'password'])->assertOk();
    $this->assertAuthenticatedAs($account, 'customer');

    $me = $this->getJson('/api/auth/me')->assertOk();

    expect(array_keys($login->json('data')))->toEqualCanonicalizing(CUSTOMER_KEYS)
        ->and(array_keys($me->json('data')))->toEqualCanonicalizing(CUSTOMER_KEYS)
        ->and($login->json('data.id'))->toBe($account->id);
});

it('refuses staff credentials on the store login', function () {
    admin(['email' => 'equipe@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'equipe@example.com', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', ['E-mail ou senha inválidos.']);

    $this->assertGuest('customer');
    $this->assertGuest('staff');
});

it('rejects invalid credentials', function () {
    customer(['email' => 'john@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'john@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest('customer');
});

it('returns the authenticated customer', function () {
    $account = customer();

    $this->actingAs($account)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', $account->email);
});

it('returns 401 for guests on /me', function () {
    assertApiError($this->getJson('/api/auth/me')->assertUnauthorized(), 'UNAUTHENTICATED', 'Não autenticado.');
});

it('logs out', function () {
    $account = customer();
    $this->postJson('/api/auth/login', ['email' => $account->email, 'password' => 'password'])->assertOk();

    $this->postJson('/api/auth/logout')->assertNoContent();

    $this->assertGuest('customer');
});

it('throttles registration and both logins after 10 attempts per minute', function (string $uri) {
    foreach (range(1, 10) as $attempt) {
        expect($this->postJson($uri, [])->status())->not->toBe(429);
    }

    $this->postJson($uri, [])->assertStatus(429);
})->with(['/api/auth/register', '/api/auth/login', '/api/admin/auth/login']);
