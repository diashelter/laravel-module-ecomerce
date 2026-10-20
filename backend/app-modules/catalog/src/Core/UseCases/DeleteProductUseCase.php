<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Catalog\Contracts\ProductOrderHistory;
use Modules\Catalog\Core\Services\ProductService;
use Modules\Catalog\Persistence\Repositories\ProductRepository;
use Modules\Shared\Exceptions\BusinessRuleException;

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
