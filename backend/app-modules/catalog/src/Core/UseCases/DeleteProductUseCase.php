<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Contracts\ProductOrderHistory;
use Modules\Catalog\Core\Services\ProductService;
use Modules\Catalog\Events\ProductDeleted;
use Modules\Catalog\Persistence\Repositories\ProductRepository;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: deletes a product that was never ordered. The row is locked to read its version, and
 * catalog.product_deleted carries that version plus 1, in the transaction of the delete. The stock row
 * leaves by the foreign key cascade and publishes nothing: the tombstone takes the product off the storefront.
 */
final class DeleteProductUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductOrderHistory $orderHistory,
        private readonly ProductService $productService,
        private readonly EventOutbox $outbox,
    ) {}

    /**
     * @throws BusinessRuleException
     * @throws ModelNotFoundException
     */
    public function execute(string $productId): void
    {
        $this->productService->ensureCanBeDeleted($this->orderHistory->hasBeenOrdered($productId));

        DB::transaction(function () use ($productId): void {
            $version = $this->products->lockById($productId)->version;

            // Stock and category links are removed by the foreign key cascades.
            $this->products->deleteById($productId);

            $this->outbox->record(new ProductDeleted($productId, $version + 1));
        });
    }
}
