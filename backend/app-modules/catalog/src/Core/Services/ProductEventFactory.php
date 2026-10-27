<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\Services;

use Modules\Catalog\Core\ValueObjects\ProductRecord;
use Modules\Catalog\Events\ProductChanged;
use Modules\Catalog\Events\ProductCreated;
use Modules\Catalog\Events\ProductStatusChanged;
use Modules\Catalog\Events\ProductUpdated;

/**
 * Builds the product events from the row a write just left: the three carry the same whole product (door 1), so the
 * projector replaces the product part without reading anything. Pure, no database.
 */
class ProductEventFactory
{
    /** @param  list<string>  $categoryIds */
    public function created(ProductRecord $product, array $categoryIds): ProductCreated
    {
        return $this->build(ProductCreated::class, $product, $categoryIds);
    }

    /** @param  list<string>  $categoryIds */
    public function updated(ProductRecord $product, array $categoryIds): ProductUpdated
    {
        return $this->build(ProductUpdated::class, $product, $categoryIds);
    }

    /** @param  list<string>  $categoryIds */
    public function statusChanged(ProductRecord $product, array $categoryIds): ProductStatusChanged
    {
        return $this->build(ProductStatusChanged::class, $product, $categoryIds);
    }

    /**
     * @template TEvent of ProductChanged
     *
     * @param  class-string<TEvent>  $event
     * @param  list<string>  $categoryIds  in ascending order
     * @return TEvent
     */
    private function build(string $event, ProductRecord $product, array $categoryIds): ProductChanged
    {
        return new $event(
            $product->id,
            $product->name,
            $product->priceCents,
            $product->description,
            $product->imageUrl,
            $product->status->value,
            $categoryIds,
            $product->createdAt->toIso8601String(),
            $product->updatedAt?->toIso8601String(),
            $product->version,
        );
    }
}
