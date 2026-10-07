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
})->with([UserRole::Support, UserRole::Admin]);

it('updates a user together with the role', function () {
    $user = support();

    $this->repository->updateWithRole($user, ['name' => 'Novo Nome'], UserRole::Admin);

    expect($user->fresh()->name)->toBe('Novo Nome')
        ->and($user->fresh()->role)->toBe(UserRole::Admin);
});

it('paginates users newest first', function () {
    $older = support(['created_at' => now()->subDay()]);
    $newer = support(['created_at' => now()]);

    $page = $this->repository->paginateNewestFirst(15);

    expect($page->pluck('id')->take(2)->all())->toBe([$newer->id, $older->id]);
});
