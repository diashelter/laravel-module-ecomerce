<?php

use App\Modules\Identity\Enums\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Sanctum only starts a session for "stateful" requests coming from the SPA domain.
beforeEach(fn () => $this->withHeader('Origin', 'http://localhost'));

const STAFF_KEYS = ['created_at', 'email', 'id', 'name', 'role', 'role_label'];

it('logs a staff member into the admin', function (UserRole $role, string $label) {
    $member = admin(['email' => 'equipe@example.com', 'role' => $role]);

    $login = $this->postJson('/api/admin/auth/login', ['email' => 'equipe@example.com', 'password' => 'password'])->assertOk();
    $this->assertAuthenticatedAs($member, 'staff');
    $this->assertGuest('customer');

    // Same session on the next request: the guard is read from the session store again.
    app('auth')->forgetGuards();
    $me = $this->withCookie(config('session.cookie'), app('session')->getId())->getJson('/api/admin/auth/me')->assertOk();

    expect(array_keys($login->json('data')))->toEqualCanonicalizing(STAFF_KEYS)
        ->and(array_keys($me->json('data')))->toEqualCanonicalizing(STAFF_KEYS)
        ->and($login->json('data.role'))->toBe($role->value)
        ->and($login->json('data.role_label'))->toBe($label)
        ->and($me->json('data.id'))->toBe($member->id);
})->with([
    'admin' => [UserRole::Admin, 'Administrador'],
    'support' => [UserRole::Support, 'Suporte'],
]);

it('refuses customer credentials on the admin login', function () {
    customer(['email' => 'cli@example.com']);

    $this->postJson('/api/admin/auth/login', ['email' => 'cli@example.com', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', ['E-mail ou senha inválidos.']);

    $this->assertGuest('staff');
    $this->assertGuest('customer');
});

it('refuses a wrong password on the admin login', function () {
    admin(['email' => 'equipe@example.com']);

    $this->postJson('/api/admin/auth/login', ['email' => 'equipe@example.com', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', ['E-mail ou senha inválidos.']);

    $this->assertGuest('staff');
});

it('logs a staff member out of the admin', function () {
    admin(['email' => 'equipe@example.com']);
    $this->postJson('/api/admin/auth/login', ['email' => 'equipe@example.com', 'password' => 'password'])->assertOk();
    $session = app('session')->getId();

    app('auth')->forgetGuards();
    $this->withCookie(config('session.cookie'), $session)->postJson('/api/admin/auth/logout')->assertNoContent();

    app('auth')->forgetGuards();
    $this->withCookie(config('session.cookie'), $session)->getJson('/api/admin/auth/me')->assertUnauthorized();
});

it('accepts only admin and support roles in the database', function (?string $role) {
    $row = fn (string $email, ?string $role) => array_filter(
        ['name' => 'Ana', 'email' => $email, 'password' => 'x', 'role' => $role],
        fn ($value, $key) => $key !== 'role' || $role !== null,
        ARRAY_FILTER_USE_BOTH,
    );

    // The nested transaction is a savepoint: Postgres aborts the test transaction after a failed statement.
    expect(fn () => DB::transaction(fn () => DB::table('users')->insert($row('bad@example.com', $role))))
        ->toThrow(QueryException::class);

    expect(DB::table('users')->insert($row('admin@example.com', 'admin')))->toBeTrue()
        ->and(DB::table('users')->insert($row('support@example.com', 'support')))->toBeTrue();
})->with([
    'absent' => [null],
    'customer' => ['customer'],
    'superadmin' => ['superadmin'],
]);
