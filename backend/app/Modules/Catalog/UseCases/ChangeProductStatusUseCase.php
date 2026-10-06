<?php

declare(strict_types=1);

namespace App\Modules\Catalog\UseCases;

use App\Modules\Catalog\DTOs\UpdateProductStatusDTO;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;

/**
 * Admin: activates or deactivates a product.
 */
final class ChangeProductStatusUseCase
{
    public function __construct(private readonly ProductRepository $products) {}

    public function execute(Product $product, UpdateProductStatusDTO $data): Product
    {
        $this->products->update($product, ['status' => $data->status]);

        return $product->load(['categories', 'stock']);
    }
}
