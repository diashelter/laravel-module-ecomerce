<?php

use App\Modules\Identity\Models\CustomerAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Sanctum only starts a session for "stateful" requests coming from the SPA domain.
beforeEach(fn () => $this->withHeader('Origin', 'http://localhost'));

const EMAIL_IN_USE = ['O valor informado para e-mail já está em uso.'];

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ana',
        'email' => 'ana@x.com',
        'password' => 'abcd1234',
        'password_confirmation' => 'abcd1234',
    ], $overrides);
}

/** Every account route that takes an e-mail, each one ready to be called with an e-mail and a password. */
function accountRoutes(): array
{
    return [
        'register' => fn ($test, array $data) => $test->postJson('/api/auth/register', registerPayload($data)),
        'login' => fn ($test, array $data) => $test->postJson('/api/auth/login', ['password' => 'password', ...$data]),
        'admin create' => function ($test, array $data) {
            $test->actingAs(admin());

            return $test->postJson('/api/admin/customers', registerPayload($data));
        },
        'admin update' => function ($test, array $data) {
            $test->actingAs(admin());

            return $test->putJson('/api/admin/customers/'.customer()->id, ['name' => 'Ana', ...$data]);
        },
        'profile' => fn ($test, array $data) => $test->actingAs(customer())->putJson('/api/account/profile', ['name' => 'Ana', ...$data]),
    ];
}

// S1 - e-mail as a single account

it('keeps the email normalized constraint on both account tables', function (string $table) {
    $definition = DB::selectOne(
        'select pg_get_constraintdef(oid) as definition from pg_constraint where conname = ? and conrelid = ?::regclass',
        ["{$table}_email_normalized", $table],
    );

    expect($definition->definition)->toBe('CHECK (((email)::text = lower(btrim((email)::text))))');
})->with(['users', 'customers']);

it('rejects a non normalized email written directly', function () {
    // The nested transaction is a savepoint: Postgres aborts the test transaction after a failed statement.
    expect(fn () => DB::transaction(fn () => DB::table('customers')->insert([
        'name' => 'Ana', 'email' => 'Ana@x.com', 'password' => 'x',
    ])))->toThrow(QueryException::class, 'customers_email_normalized');

    expect(DB::table('customers')->whereRaw('lower(email) = ?', ['ana@x.com'])->count())->toBe(0);
});

it('registers with the email normalized', function () {
    $this->postJson('/api/auth/register', registerPayload(['email' => 'Ana@X.com']))
        ->assertCreated()
        ->assertJsonPath('data.email', 'ana@x.com');

    expect(CustomerAccount::query()->where('email', 'ana@x.com')->count())->toBe(1);
});

it('rejects registering an email that differs only in case', function () {
    customer(['email' => 'ana@x.com']);
    $before = CustomerAccount::query()->count();

    $this->postJson('/api/auth/register', registerPayload(['email' => 'ANA@x.com']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', EMAIL_IN_USE);

    expect(CustomerAccount::query()->count())->toBe($before);
});

it('logs in with the email in any case', function () {
    $user = customer(['email' => 'ana@x.com']);

    $this->postJson('/api/auth/login', ['email' => 'ANA@X.COM', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.email', 'ana@x.com');

    $this->assertAuthenticatedAs($user, 'customer');
});

it('rejects invalid credentials with the generic message', function () {
    $this->postJson('/api/auth/login', ['email' => 'nobody@x.com', 'password' => 'whatever'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', ['E-mail ou senha inválidos.']);

    $this->assertGuest('customer');
});

it('keeps the own email when only its case changes', function () {
    $user = customer(['email' => 'ana@x.com']);

    $this->actingAs($user)->putJson('/api/account/profile', ['name' => 'Ana', 'email' => 'ANA@x.com'])
        ->assertOk()
        ->assertJsonPath('data.email', 'ana@x.com');

    expect($user->fresh()->email)->toBe('ana@x.com');
});

it('does not allow taking another user email in another case', function () {
    customer(['email' => 'bruno@x.com']);
    $user = customer(['email' => 'ana@x.com']);

    $this->actingAs($user)->putJson('/api/account/profile', ['name' => 'Ana', 'email' => 'BRUNO@x.com'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', EMAIL_IN_USE);

    expect($user->fresh()->email)->toBe('ana@x.com');
});

it('creates a customer with the email normalized', function () {
    $this->actingAs(admin())->postJson('/api/admin/customers', registerPayload(['name' => 'Carla', 'email' => 'Carla@X.com']))
        ->assertCreated()
        ->assertJsonPath('data.email', 'carla@x.com');

    expect(CustomerAccount::query()->where('email', 'carla@x.com')->exists())->toBeTrue();
});

it('does not let the admin reuse another email in another case', function () {
    customer(['email' => 'bruno@x.com']);
    $target = customer(['email' => 'ana@x.com']);

    $this->actingAs(admin())->putJson("/api/admin/customers/{$target->id}", ['name' => 'Ana', 'email' => 'Bruno@X.com'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', EMAIL_IN_USE);

    expect($target->fresh()->email)->toBe('ana@x.com');
});

it('rejects a malformed email on every account route', function (string $route) {
    accountRoutes()[$route]($this, ['email' => 'ana'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', ['O campo e-mail deve ser um e-mail válido.']);
})->with(array_keys(accountRoutes()));

it('bounds the email at 255 characters', function () {
    $address = fn (int $lastLabel) => str_repeat('a', 64).'@'.str_repeat('b', 62).'.'.str_repeat('c', 62).'.'.str_repeat('d', 62).'.'.str_repeat('e', $lastLabel);

    expect(strlen($address(1)))->toBe(255)->and(strlen($address(2)))->toBe(256);

    $this->postJson('/api/auth/register', registerPayload(['email' => $address(2)]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.email', ['O campo e-mail não pode ter mais de 255 caracteres.']);

    $this->postJson('/api/auth/register', registerPayload(['email' => $address(1)]))->assertCreated();
});

it('rejects an email that is not a string', function () {
    $this->postJson('/api/auth/register', registerPayload(['email' => ['ana@x.com']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

// S2 - password policy in one place

it('rejects a password shorter than 8 characters on every route that chooses one', function (string $route) {
    $short = ['password' => 'abc1234', 'password_confirmation' => 'abc1234', 'email' => 'new@x.com'];

    accountRoutes()[$route]($this, [...$short, 'current_password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'O campo senha deve ter pelo menos 8 caracteres.');
})->with(['register', 'admin create', 'admin update', 'profile']);

it('stores the hash of a chosen password', function () {
    $this->postJson('/api/auth/register', registerPayload())->assertCreated();

    expect(Hash::check('abcd1234', CustomerAccount::query()->where('email', 'ana@x.com')->sole()->password))->toBeTrue();
});

it('logs in with a password shorter than the current policy', function () {
    customer(['email' => 'ana@x.com', 'password' => Hash::make('abc123')]);

    $this->postJson('/api/auth/login', ['email' => 'ana@x.com', 'password' => 'abc123'])->assertOk();
});

it('keeps the password when none is sent on profile and admin updates', function () {
    $user = customer(['email' => 'ana@x.com']);
    $hash = $user->password;

    $this->actingAs($user)->putJson('/api/account/profile', ['name' => 'Ana 2', 'email' => 'ana@x.com'])->assertOk();
    expect($user->fresh()->password)->toBe($hash);

    $this->actingAs(admin())->putJson("/api/admin/customers/{$user->id}", ['name' => 'Ana 3', 'email' => 'ana@x.com'])->assertOk();
    expect($user->fresh()->password)->toBe($hash);
});
