<?php

declare(strict_types=1);

namespace App\Modules\Catalog\UseCases;

use App\Modules\Catalog\DTOs\ProductDTO;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;

/**
 * Admin: updates product data and categories. Stock is intentionally not touched
 * here (see AdjustStockUseCase).
 */
final class UpdateProductUseCase
{
    public function __construct(private readonly ProductRepository $products) {}

    public function execute(Product $product, ProductDTO $data): Product
    {
        DB::transaction(function () use ($product, $data): void {
            $this->products->update($product, $data->attributes());
            $this->products->syncCategories($product, $data->categoryIds);
        });

        return $product->load(['categories', 'stock']);
    }
}
