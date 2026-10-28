<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\Services;

use Modules\Catalog\Events\ProductChanged;

/**
 * The product part of the storefront document (door 4), from the whole product a product event carries. The part is
 * replaced whole, so it holds exactly these keys: a field the event does not bring does not survive. Pure, no database.
 */
class ProductPartFactory
{
    /** @return array<string, mixed> */
    public function from(ProductChanged $event): array
    {
        return [
            'name' => $event->name,
            'price_cents' => $event->priceCents,
            'description' => $event->description,
            'image_url' => $event->imageUrl,
            'status' => $event->status,
            'category_ids' => $event->categoryIds,
            'created_at' => $event->createdAt,
            'updated_at' => $event->updatedAt,
            'version' => $event->version,
        ];
    }
}
