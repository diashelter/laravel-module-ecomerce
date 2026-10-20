<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\DTOs\ProductDTO;
use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Persistence\Repositories\ProductRepository;

/**
 * Admin: updates product data and categories. Stock is intentionally not touched
 * here (see AdjustStockUseCase).
 */
final class UpdateProductUseCase
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @throws ModelNotFoundException
     */
    public function execute(string $productId, ProductDTO $data): ProductRecord
    {
        $product = $this->products->findOrFail($productId);

        DB::transaction(function () use ($product, $data): void {
            $this->products->update($product, $data->attributes());
            $this->products->syncCategories($product, $data->categoryIds);
        });

        return $product->toRecord();
    }
}
