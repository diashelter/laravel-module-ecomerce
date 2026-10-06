<?php

use App\Modules\Catalog\Models\Category;

beforeEach(fn () => $this->actingAs(admin()));

it('creates a category generating the slug from the name', function () {
    $this->postJson('/api/admin/categories', ['name' => 'Eletrônicos'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'eletronicos');
});

it('does not allow duplicated slugs', function () {
    Category::factory()->create(['name' => 'Eletrônicos']);

    $this->postJson('/api/admin/categories', ['name' => 'eletronicos'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('updates a category and regenerates its slug', function () {
    $category = Category::factory()->create(['name' => 'Casa']);

    $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'Casa e Jardim'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'casa-e-jardim');

    // Keeping the same name is not a duplicate of itself.
    $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'Casa e Jardim'])->assertOk();
});

it('deletes a category without products', function () {
    $category = Category::factory()->create();

    $this->deleteJson("/api/admin/categories/{$category->id}")->assertNoContent();

    $this->assertModelMissing($category);
});

it('blocks deleting a category with products', function () {
    $category = Category::factory()->create();
    productWithStock(1)->categories()->attach($category);

    $this->deleteJson("/api/admin/categories/{$category->id}")->assertConflict();

    $this->assertModelExists($category);
});

it('lists categories with product counts', function () {
    $category = Category::factory()->create();
    productWithStock(1)->categories()->attach($category);

    $this->getJson('/api/admin/categories')->assertOk()->assertJsonPath('data.0.products_count', 1);
});
