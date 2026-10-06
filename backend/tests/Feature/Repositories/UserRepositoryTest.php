<?php

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Repositories\UserRepository;

beforeEach(fn () => $this->repository = app(UserRepository::class));

it('creates a user with an explicit role', function (UserRole $role) {
    $user = $this->repository->createWithRole([
        'name' => 'Maria',
        'email' => 'maria@example.com',
        'password' => 'secret-password',
    ], $role);

    expect($user->exists)->toBeTrue()
        ->and($user->fresh()->role)->toBe($role);
})->with([UserRole::Customer, UserRole::Admin]);

it('paginates users newest first', function () {
    $older = customer(['created_at' => now()->subDay()]);
    $newer = customer(['created_at' => now()]);

    $page = $this->repository->paginateNewestFirst(15);

    expect($page->pluck('id')->take(2)->all())->toBe([$newer->id, $older->id]);
});

it('counts only customers', function () {
    customer();
    customer();
    admin();

    expect($this->repository->countCustomers())->toBe(2);
});
