<?php

declare(strict_types=1);

namespace App\Modules\Catalog\UseCases;

use App\Modules\Catalog\Contracts\ProductOrderHistory;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     * @throws ModelNotFoundException
     */
    public function execute(string $productId): void
    {
        $this->productService->ensureCanBeDeleted($this->orderHistory->hasBeenOrdered($productId));

        // Stock and category links are removed by the foreign key cascades.
        $this->products->deleteById($productId);
    }
}
