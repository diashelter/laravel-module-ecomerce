<?php

use App\Modules\Catalog\DTOs\CategoryDTO;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\UseCases\CreateCategoryUseCase;
use App\Modules\Catalog\UseCases\DeleteCategoryUseCase;
use App\Modules\Catalog\UseCases\UpdateCategoryUseCase;
use App\Modules\Shared\Exceptions\BusinessRuleException;

it('creates a category with the product count loaded', function () {
    $category = app(CreateCategoryUseCase::class)->execute(new CategoryDTO('Eletrônicos'));

    expect($category->exists)->toBeTrue()
        ->and($category->slug)->toBe('eletronicos')
        ->and($category->products_count)->toBe(0);
});

it('updates a category and regenerates its slug', function () {
    $category = Category::factory()->create(['name' => 'Antigo']);
    productWithStock(1)->categories()->attach($category);

    $updated = app(UpdateCategoryUseCase::class)->execute($category, new CategoryDTO('Novo Nome'));

    expect($updated->slug)->toBe('novo-nome')
        ->and($updated->products_count)->toBe(1);
});

it('does not delete a category that has products', function () {
    $category = Category::factory()->create();
    productWithStock(1)->categories()->attach($category);

    expect(fn () => app(DeleteCategoryUseCase::class)->execute($category))
        ->toThrow(BusinessRuleException::class, 'Esta categoria possui produtos associados e não pode ser excluída.');

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('deletes an empty category', function () {
    $category = Category::factory()->create();

    app(DeleteCategoryUseCase::class)->execute($category);

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});
