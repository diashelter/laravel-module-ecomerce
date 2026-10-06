<?php

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\User;

// Sanctum only starts a session for "stateful" requests coming from the SPA domain.
beforeEach(fn () => $this->withHeader('Origin', 'http://localhost'));

it('registers a customer and ignores any role sent by the client', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Maria',
        'email' => 'maria@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'admin',
    ]);

    $response->assertCreated()->assertJsonPath('data.role', 'customer');
    expect(User::query()->where('email', 'maria@example.com')->first()->role)->toBe(UserRole::Customer);
    $this->assertAuthenticated('web');
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

it('logs in with valid credentials and returns the user', function () {
    $user = customer(['email' => 'john@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'john@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user, 'web');
});

it('rejects invalid credentials', function () {
    customer(['email' => 'john@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'john@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest('web');
});

it('returns the authenticated user', function () {
    $user = customer();

    $this->actingAs($user)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
});

it('returns 401 for guests on /me', function () {
    assertApiError($this->getJson('/api/auth/me')->assertUnauthorized(), 'UNAUTHENTICATED', 'Não autenticado.');
});

it('logs out', function () {
    $user = customer();
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

    $this->postJson('/api/auth/logout')->assertNoContent();

    $this->assertGuest('web');
});
