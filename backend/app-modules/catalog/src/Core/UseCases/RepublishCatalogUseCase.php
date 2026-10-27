<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Core\Services\ProductEventFactory;
use Modules\Catalog\Events\CategoryUpdated;
use Modules\Catalog\Persistence\Queries\RepublicationQuery;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Shared\Exceptions\RepublicationFailedException;
use Throwable;

/**
 * Rebuild of the storefront, the catalog's part: records one catalog.category_updated per category and one
 * catalog.product_updated per product through the outbox, with the state and the version the row has now (no version
 * is added: nothing was written). Each record is its own transaction, so a failure halfway leaves exactly the
 * records it counted, and a second run converges to the same storefront. A product is read with its category ids in
 * one statement, so the two belong to one snapshot.
 */
final class RepublishCatalogUseCase
{
    public function __construct(
        private readonly RepublicationQuery $query,
        private readonly ProductEventFactory $events,
        private readonly EventOutbox $outbox,
    ) {}

    /**
     * @return array{categories: int, products: int} how many of each it recorded
     *
     * @throws RepublicationFailedException
     */
    public function execute(): array
    {
        $categories = 0;
        $products = 0;

        try {
            foreach ($this->query->categories() as $category) {
                DB::transaction(fn () => $this->outbox->record(new CategoryUpdated($category->id, $category->name, $category->slug, $category->version)));
                $categories++;
            }

            foreach ($this->query->productIds() as $productId) {
                $recorded = DB::transaction(function () use ($productId): bool {
                    $snapshot = $this->query->snapshotOf($productId);

                    if ($snapshot === null) {
                        return false;
                    }

                    $this->outbox->record($this->events->updated($snapshot->product, $snapshot->categoryIds));

                    return true;
                });

                $products += $recorded ? 1 : 0;
            }
        } catch (Throwable $thrown) {
            throw new RepublicationFailedException($categories + $products, $thrown);
        }

        return ['categories' => $categories, 'products' => $products];
    }
}
