<?php

declare(strict_types=1);

namespace App\Modules\Catalog\UseCases;

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: deletes a product that was never ordered.
 */
final class DeleteProductUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductOrderHistory $orderHistory,
        private readonly ProductService $productService,
    ) {}

    /**
     * @throws BusinessRuleException
     */
    public function execute(Product $product): void
    {
        $this->productService->ensureCanBeDeleted($this->orderHistory->hasBeenOrdered($product->id));

        // Stock and category links are removed by the foreign key cascades.
        $this->products->delete($product);
    }
}
