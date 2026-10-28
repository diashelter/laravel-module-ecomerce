<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\InboundEvents;

use Modules\Messaging\Contracts\DomainEvent;

/**
 * How the catalog reads inventory.stock_changed, published by the inventory module: the published format is the
 * name, the version and the payload keys, never the producer's class, so the catalog names nothing of the
 * inventory module. Only the consumer and the provider use it: the inventory records the event, never this class.
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
