<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\DTOs\UpdateProductStatusDTO;
use Modules\Catalog\Core\Services\ProductEventFactory;
use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Persistence\Repositories\ProductRepository;
use Modules\Messaging\Contracts\EventOutbox;

/**
 * Admin: activates or deactivates a product. catalog.product_status_changed carries the whole product, not only the
 * status, because the projector replaces the product part whole.
 */
final class ChangeProductStatusUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductEventFactory $events,
        private readonly EventOutbox $outbox,
    ) {}

    /**
     * @throws ModelNotFoundException
     */
    public function execute(string $productId, UpdateProductStatusDTO $data): ProductRecord
    {
        return DB::transaction(function () use ($productId, $data): ProductRecord {
            $product = $this->products->lockById($productId);

            $this->products->update($product, ['status' => $data->status, 'version' => $product->version + 1]);

            $record = $product->toRecord();

            $this->outbox->record($this->events->statusChanged($record, $this->products->categoryIdsOf($productId)));

            return $record;
        });
    }
}
