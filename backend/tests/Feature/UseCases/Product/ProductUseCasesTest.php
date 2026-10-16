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
use App\Modules\Catalog\ValueObjects\ProductRecord;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Models\OrderItem;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    expect($product->imageUrl)->toBe("https://picsum.photos/seed/product-{$product->id}/600/600")
        ->and(Product::query()->findOrFail($product->id)->image_url)->toBe($product->imageUrl)
        ->and(Product::query()->findOrFail($product->id)->categories()->pluck('categories.id')->sort()->values()->all())->toBe(collect($categoryIds)->sort()->values()->all())
        ->and(Stock::query()->where('product_id', $product->id)->value('quantity'))->toBe(7);
});

it('returns the written product data from every product write', function () {
    $category = Category::factory()->create();
    $product = productWithStock(4);
    $dto = ($this->product)([$category->id], ['name' => 'Monitor 32', 'priceCents' => 99900]);

    $created = app(CreateProductUseCase::class)->execute(new CreateProductDTO($dto, 3));
    $createdRow = Product::query()->findOrFail($created->id);
    $updated = app(UpdateProductUseCase::class)->execute($product->id, $dto);
    $updatedRow = Product::query()->findOrFail($product->id);
    $changed = app(ChangeProductStatusUseCase::class)->execute($product->id, new UpdateProductStatusDTO(ProductStatus::Inactive));
    $changedRow = Product::query()->findOrFail($product->id);

    foreach ([[$created, $createdRow], [$updated, $updatedRow], [$changed, $changedRow]] as [$record, $row]) {
        expect($record)->toBeInstanceOf(ProductRecord::class)
            ->and($record->id)->toBe($row->id)
            ->and($record->name)->toBe($row->name)
            ->and($record->priceCents)->toBe($row->price_cents)
            ->and($record->description)->toBe($row->description)
            ->and($record->status)->toBe($row->status)
            ->and($record->createdAt?->toIso8601String())->toBe($row->created_at->toIso8601String())
            ->and($record->updatedAt?->toIso8601String())->toBe($row->updated_at->toIso8601String());
    }

    expect($created->name)->toBe('Monitor 32')
        ->and($created->priceCents)->toBe(99900)
        ->and($updated->priceCents)->toBe(99900)
        ->and($changed->status)->toBe(ProductStatus::Inactive);
});

it('keeps the image url when one is given', function () {
    $category = Category::factory()->create();

    $product = app(CreateProductUseCase::class)->execute(new CreateProductDTO(
        ($this->product)([$category->id], ['imageUrl' => 'https://example.com/monitor.png']),
        1,
    ));

    expect(Product::query()->findOrFail($product->id)->image_url)->toBe('https://example.com/monitor.png');
});

it('rolls back the product when the categories cannot be synced', function () {
    expect(fn () => app(CreateProductUseCase::class)->execute(new CreateProductDTO(($this->product)([unknownId()]), 5)))
        ->toThrow(QueryException::class);

    expect(Product::query()->count())->toBe(0)
        ->and(Stock::query()->count())->toBe(0);
});

it('rolls back the product when the stock cannot be opened', function () {
    $category = Category::factory()->create();

    // A negative quantity is refused by the stocks CHECK constraint, inside the same transaction.
    expect(fn () => app(CreateProductUseCase::class)->execute(new CreateProductDTO(($this->product)([$category->id]), -1)))
        ->toThrow(QueryException::class);

    expect(Product::query()->count())->toBe(0)
        ->and(Stock::query()->count())->toBe(0);
});

it('updates the product and its categories without touching the stock', function () {
    $product = productWithStock(4);
    $product->categories()->attach(Category::factory()->create());
    $newCategory = Category::factory()->create();

    $updated = app(UpdateProductUseCase::class)->execute($product->id, ($this->product)([$newCategory->id], ['name' => 'Monitor 32']));

    expect($updated->name)->toBe('Monitor 32')
        ->and($product->categories()->pluck('categories.id')->all())->toBe([$newCategory->id])
        ->and(stockOf($product)->quantity)->toBe(4);
});

it('changes the product status', function () {
    $product = productWithStock(4);

    $updated = app(ChangeProductStatusUseCase::class)->execute($product->id, new UpdateProductStatusDTO(ProductStatus::Inactive));

    expect($updated->status)->toBe(ProductStatus::Inactive)
        ->and($product->fresh()->status)->toBe(ProductStatus::Inactive);
});

it('does not delete a product that appears in orders', function () {
    $product = productWithStock(4);
    OrderItem::factory()->forProduct($product, 1)->create();

    expect(fn () => app(DeleteProductUseCase::class)->execute($product->id))
        ->toThrow(BusinessRuleException::class, 'Este produto possui pedidos e não pode ser excluído. Desative-o em vez disso.');

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();
});

it('deletes a product without orders together with its stock', function () {
    $product = productWithStock(4);

    app(DeleteProductUseCase::class)->execute($product->id);

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse()
        ->and(Stock::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

it('deletes a product by id and refuses an unknown one', function () {
    $product = productWithStock(4);
    $other = productWithStock(2);

    app(DeleteProductUseCase::class)->execute($product->id);

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();

    expect(fn () => app(DeleteProductUseCase::class)->execute(unknownId()))->toThrow(ModelNotFoundException::class);

    expect(Product::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('refuses to update or change the status of an unknown product', function () {
    expect(fn () => app(UpdateProductUseCase::class)->execute(unknownId(), ($this->product)([Category::factory()->create()->id])))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn () => app(ChangeProductStatusUseCase::class)->execute(unknownId(), new UpdateProductStatusDTO(ProductStatus::Inactive)))
        ->toThrow(ModelNotFoundException::class);
});
