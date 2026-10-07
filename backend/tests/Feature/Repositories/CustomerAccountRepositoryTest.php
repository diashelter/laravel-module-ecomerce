<?php

use App\Modules\Identity\Repositories\CustomerAccountRepository;

beforeEach(fn () => $this->repository = app(CustomerAccountRepository::class));

it('paginates customer accounts newest first', function () {
    $older = customer(['created_at' => now()->subDay()]);
    $newer = customer(['created_at' => now()]);

    $page = $this->repository->paginateNewestFirst(15);

    expect($page->pluck('id')->take(2)->all())->toBe([$newer->id, $older->id]);
});

it('counts only customer accounts', function () {
    customer();
    customer();
    admin();

    expect($this->repository->count())->toBe(2);
});
