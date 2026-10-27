<?php

declare(strict_types=1);

namespace Modules\Inventory\Events;

use Modules\Messaging\Contracts\DomainEvent;

/**
 * Inventory: the quantity of a stock row changed (opened, adjusted by the admin or sold by a checkout, or
 * republished by the rebuild). It carries the quantity the row holds after the write, never the units moved, and the
 * version of the row, so the storefront projector replaces the stock part whole.
 */
final class StockChanged implements DomainEvent
{
    public function __construct(
        public string $stockId,
        public string $productId,
        public int $quantity,
        public int $version,
    ) {}

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
        return [
            'stock_id' => $this->stockId,
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'version' => $this->version,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self($payload['stock_id'], $payload['product_id'], $payload['quantity'], $payload['version']);
    }
}
