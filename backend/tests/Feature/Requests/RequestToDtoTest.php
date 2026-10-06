<?php

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Http\Requests\Admin\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\ProductIndexRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Customers\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockOperation;
use App\Modules\Inventory\Http\Requests\Admin\UpdateStockRequest;
use App\Modules\Ordering\DTOs\CartItemDTO;
use App\Modules\Ordering\Http\Requests\ValidateCartRequest;
use App\Modules\Shared\Http\Requests\ApiFormRequest;

/**
 * @template T of ApiFormRequest
 *
 * @param  class-string<T>  $class
 * @return T
 */
function validatedRequest(string $class, array $data, ?User $user = null): ApiFormRequest
{
    $request = $class::create('/', 'POST', $data);
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $user);
    $request->validateResolved();

    return $request;
}

it('converts cart items into typed DTOs grouped by product', function () {
    $cart = validatedRequest(ValidateCartRequest::class, ['items' => [
        ['product_id' => '7', 'quantity' => '2'],
        ['product_id' => 3, 'quantity' => 1],
        ['product_id' => 7, 'quantity' => 4],
    ]])->toDto();

    expect($cart->items)->toHaveCount(3)
        ->and($cart->items[0])->toEqual(new CartItemDTO(7, 2))
        ->and($cart->quantitiesByProduct())->toBe([3 => 1, 7 => 6]);
});

it('converts the product payload into a CreateProductDTO', function () {
    $categories = Category::factory()->count(2)->create();

    $dto = validatedRequest(StoreProductRequest::class, [
        'name' => 'Monitor 27',
        'price_cents' => '129990',
        'description' => 'Monitor IPS',
        'status' => 'active',
        'category_ids' => $categories->pluck('id')->map(fn (int $id) => (string) $id)->all(),
        'stock_quantity' => '5',
    ])->toDto();

    expect($dto->stockQuantity)->toBe(5)
        ->and($dto->product->priceCents)->toBe(129990)
        ->and($dto->product->status)->toBe(ProductStatus::Active)
        ->and($dto->product->imageUrl)->toBeNull()
        ->and($dto->product->categoryIds)->toBe($categories->pluck('id')->all());
});

it('maps an empty password to null on profile updates', function () {
    $user = customer();
    $this->actingAs($user);

    $dto = validatedRequest(UpdateProfileRequest::class, [
        'name' => 'Novo Nome',
        'email' => 'novo@example.com',
        'password' => '',
    ], $user)->toDto();

    expect($dto->name)->toBe('Novo Nome')
        ->and($dto->email)->toBe('novo@example.com')
        ->and($dto->password)->toBeNull();
});

it('converts the stock operation into its enum', function () {
    $dto = validatedRequest(UpdateStockRequest::class, ['operation' => 'decrease', 'quantity' => '3'])->toDto();

    expect($dto->operation)->toBe(StockOperation::Decrease)
        ->and($dto->quantity)->toBe(3);
});

it('defaults the catalog sort to name', function () {
    $dto = validatedRequest(ProductIndexRequest::class, ['category' => 'games'])->toDto();

    expect($dto->category)->toBe('games')
        ->and($dto->sort)->toBe('name');
});
