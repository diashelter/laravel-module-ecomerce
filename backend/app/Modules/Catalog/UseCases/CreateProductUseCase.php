<?php

declare(strict_types=1);

namespace App\Modules\Catalog\UseCases;

use App\Modules\Catalog\DTOs\CreateProductDTO;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Inventory\Contracts\StockInitializer;
use Illuminate\Support\Facades\DB;

/**
 * Admin: creates the product, its category links and its stock row in a single transaction.
 */
final class CreateProductUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly StockInitializer $stockInitializer,
        private readonly ProductService $productService,
    ) {}

    public function execute(CreateProductDTO $data): Product
    {
        $product = DB::transaction(function () use ($data): Product {
            $product = $this->products->create($data->product->attributes());

            if (blank($product->image_url)) {
                $this->products->update($product, [
                    'image_url' => $this->productService->defaultImageUrl($product->id),
                ]);
            }

            $this->products->syncCategories($product, $data->product->categoryIds);
            $this->stockInitializer->createForProduct($product->id, $data->stockQuantity);

            return $product;
        });

        return $product->load(['categories', 'stock']);
    }
}
