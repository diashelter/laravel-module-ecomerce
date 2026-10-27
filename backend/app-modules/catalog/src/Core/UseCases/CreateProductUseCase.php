<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\DTOs\CreateProductDTO;
use Modules\Catalog\Core\Services\ProductEventFactory;
use Modules\Catalog\Core\Services\ProductService;
use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Persistence\Repositories\ProductRepository;
use Modules\Inventory\Contracts\StockInitializer;
use Modules\Messaging\Contracts\EventOutbox;

/**
 * Admin: creates the product, its category links and its stock row in a single transaction, and records
 * catalog.product_created (version 1) in that same transaction. The stock opening records its own event.
 */
final class CreateProductUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly StockInitializer $stockInitializer,
        private readonly ProductService $productService,
        private readonly ProductEventFactory $events,
        private readonly EventOutbox $outbox,
    ) {}

    public function execute(CreateProductDTO $data): ProductRecord
    {
        return DB::transaction(function () use ($data): ProductRecord {
            $product = $this->products->create([...$data->product->attributes(), 'version' => 1]);

            if (blank($product->image_url)) {
                // The version stays 1: the stored default image is part of the creation, not a second write.
                $this->products->update($product, [
                    'image_url' => $this->productService->defaultImageUrl($product->id),
                ]);
            }

            $this->products->syncCategories($product, $data->product->categoryIds);
            $this->stockInitializer->createForProduct($product->id, $data->stockQuantity);

            $record = $product->toRecord();

            $this->outbox->record($this->events->created($record, $this->products->categoryIdsOf($product->id)));

            return $record;
        });
    }
}
