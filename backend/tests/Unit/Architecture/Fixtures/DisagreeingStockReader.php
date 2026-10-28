<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures;

use Modules\Messaging\Contracts\DomainEvent;

/**
 * A reader of inventory.stock_changed that reads the quantity under the wrong key: the compatibility check between
 * a reader and its producer must catch it.
 */
final class DisagreeingStockReader implements DomainEvent
{
    public function __construct(public string $stockId, public string $productId, public int $units, public int $version) {}

    public static function eventName(): string
    {
        return 'inventory.stock_changed';
    }

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return ['stock_id' => $this->stockId, 'product_id' => $this->productId, 'units' => $this->units, 'version' => $this->version];
    }

    public static function fromPayload(array $payload): static
    {
        return new self($payload['stock_id'], $payload['product_id'], (int) ($payload['units'] ?? 0), $payload['version']);
    }
}
