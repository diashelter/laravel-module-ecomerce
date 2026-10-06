<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Shared\Repositories\BaseRepository;

beforeEach(function () {
    $this->repository = new class extends BaseRepository
    {
        protected function model(): string
        {
            return Category::class;
        }
    };
});

it('creates a model through mass assignment', function () {
    $category = $this->repository->create(['name' => 'Eletrônicos']);

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->exists)->toBeTrue()
        ->and($category->slug)->toBe('eletronicos');
});

it('updates a model and returns the same instance', function () {
    $category = Category::factory()->create(['name' => 'Antigo']);

    $updated = $this->repository->update($category, ['name' => 'Novo Nome']);

    expect($updated)->toBe($category)
        ->and($category->fresh()->name)->toBe('Novo Nome');
});

it('deletes a model', function () {
    $category = Category::factory()->create();

    $this->repository->delete($category);

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('counts the rows of its model', function () {
    expect($this->repository->count())->toBe(0);

    Category::factory()->count(3)->create();

    expect($this->repository->count())->toBe(3);
});
