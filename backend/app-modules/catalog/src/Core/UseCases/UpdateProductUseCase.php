<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\DTOs\ProductDTO;
use Modules\Catalog\Core\Services\ProductEventFactory;
use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Persistence\Repositories\ProductRepository;
use Modules\Messaging\Contracts\EventOutbox;

/**
 * Admin: updates product data and categories. Stock is intentionally not touched
 * here (see AdjustStockUseCase). The row is locked before the new version is computed, and
 * catalog.product_updated carries the whole product as the locked row ends up.
 */
final class UpdateProductUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductEventFactory $events,
        private readonly EventOutbox $outbox,
    ) {}

    /**
     * @throws ModelNotFoundException
     */
    public function execute(string $productId, ProductDTO $data): ProductRecord
    {
        return DB::transaction(function () use ($productId, $data): ProductRecord {
            $product = $this->products->lockById($productId);

            $this->products->update($product, [...$data->attributes(), 'version' => $product->version + 1]);
            $this->products->syncCategories($product, $data->categoryIds);

            $record = $product->toRecord();

            $this->outbox->record($this->events->updated($record, $this->products->categoryIdsOf($productId)));

            return $record;
        });
    }
}
