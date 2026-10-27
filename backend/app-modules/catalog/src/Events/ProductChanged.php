<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

use Modules\Messaging\Contracts\DomainEvent;

/**
 * What the three product events share: the whole product as the row holds it after the write, with its categories
 * and the version of the record, so the storefront projector replaces the product part without reading anything.
 * The subclasses only name the fact (created, updated, status changed).
 */
abstract class ProductChanged implements DomainEvent
{
    /**
     * @param  list<string>  $categoryIds  in ascending order
     * @param  string  $createdAt  ISO 8601 with the offset
     * @param  string|null  $updatedAt  ISO 8601 with the offset
     */
    final public function __construct(
        public string $productId,
        public string $name,
        public int $priceCents,
        public ?string $description,
        public ?string $imageUrl,
        public string $status,
        public array $categoryIds,
        public string $createdAt,
        public ?string $updatedAt,
        public int $version,
    ) {}

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [
            'product_id' => $this->productId,
            'name' => $this->name,
            'price_cents' => $this->priceCents,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'status' => $this->status,
            'category_ids' => $this->categoryIds,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'version' => $this->version,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new static(
            $payload['product_id'],
            $payload['name'],
            $payload['price_cents'],
            $payload['description'],
            $payload['image_url'],
            $payload['status'],
            $payload['category_ids'],
            $payload['created_at'],
            $payload['updated_at'],
            $payload['version'],
        );
    }
}
