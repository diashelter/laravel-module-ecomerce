<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

use Modules\Messaging\Contracts\DomainEvent;

/** Catalog: a product was deleted. The version is the stored one plus 1, so the projector leaves a tombstone. */
final class ProductDeleted implements DomainEvent
{
    public function __construct(public string $productId, public int $version) {}

    public static function eventName(): string
    {
        return 'catalog.product_deleted';
    }

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return ['product_id' => $this->productId, 'version' => $this->version];
    }

    public static function fromPayload(array $payload): static
    {
        return new self($payload['product_id'], $payload['version']);
    }
}
