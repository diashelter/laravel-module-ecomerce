<?php

use App\Modules\Catalog\DTOs\CreateProductDTO;
use App\Modules\Catalog\DTOs\ProductDTO;
use App\Modules\Catalog\DTOs\UpdateProductStatusDTO;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\UseCases\ChangeProductStatusUseCase;
use App\Modules\Catalog\UseCases\CreateProductUseCase;
use App\Modules\Catalog\UseCases\DeleteProductUseCase;
use App\Modules\Catalog\UseCases\UpdateProductUseCase;
use App\Modules\Catalog\ValueObjects\CategoryIds;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Models\OrderItem;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->product = fn (array $categoryIds, array $overrides = []) => new ProductDTO(...[
        'name' => 'Monitor 27',
        'priceCents' => 129990,
        'description' => 'Monitor IPS',
        'imageUrl' => null,
        'status' => ProductStatus::Active,
        'categoryIds' => new CategoryIds(...$categoryIds),
        ...$overrides,
    ]);
});

it('creates a product with its categories, stock and a default image', function () {
    $categoryIds = Category::factory()->count(2)->create()->pluck('id')->all();

    $product = app(CreateProductUseCase::class)->execute(new CreateProductDTO(($this->product)($categoryIds), 7));

    expect($product->image_url)->toBe("https://picsum.photos/seed/product-{$product->id}/600/600")
        ->and($product->relationLoaded('categories'))->toBeTrue()
        ->and($product->categories)->toHaveCount(2)
        ->and($product->stock->quantity)->toBe(7)
        ->and($product->fresh()->image_url)->toBe($product->image_url);
});

it('keeps the image url when one is given', function () {
    $category = Category::factory()->create();

    $product = app(CreateProductUseCase::class)->execute(new CreateProductDTO(
        ($this->product)([$category->id], ['imageUrl' => 'https://example.com/monitor.png']),
        1,
    ));

    expect($product->fresh()->image_url)->toBe('https://example.com/monitor.png');
});

it('rolls back the product when the categories cannot be synced', function () {
    expect(fn () => app(CreateProductUseCase::class)->execute(new CreateProductDTO(($this->product)([999999]), 5)))
        ->toThrow(QueryException::class);

    expect(Product::query()->count())->toBe(0)
        ->and(Stock::query()->count())->toBe(0);
});

it('updates the product and its categories without touching the stock', function () {
    $product = productWithStock(4);
    $product->categories()->attach(Category::factory()->create());
    $newCategory = Category::factory()->create();

    $updated = app(UpdateProductUseCase::class)->execute($product, ($this->product)([$newCategory->id], ['name' => 'Monitor 32']));

    expect($updated->name)->toBe('Monitor 32')
        ->and($updated->categories->pluck('id')->all())->toBe([$newCategory->id])
        ->and($updated->stock->quantity)->toBe(4);
});

it('changes the product status', function () {
    $product = productWithStock(4);

    $updated = app(ChangeProductStatusUseCase::class)->execute($product, new UpdateProductStatusDTO(ProductStatus::Inactive));

    expect($updated->status)->toBe(ProductStatus::Inactive)
        ->and($product->fresh()->status)->toBe(ProductStatus::Inactive)
        ->and($updated->relationLoaded('stock'))->toBeTrue();
});

it('does not delete a product that appears in orders', function () {
    $product = productWithStock(4);
    OrderItem::factory()->forProduct($product, 1)->create();

    expect(fn () => app(DeleteProductUseCase::class)->execute($product))
        ->toThrow(BusinessRuleException::class, 'Este produto possui pedidos e não pode ser excluído. Desative-o em vez disso.');

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('deletes a product without orders together with its stock', function () {
    $product = productWithStock(4);

    app(DeleteProductUseCase::class)->execute($product);

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse()
        ->and(Stock::query()->where('product_id', $product->id)->exists())->toBeFalse();
});
