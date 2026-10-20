<?php

declare(strict_types=1);

namespace Modules\Ordering\Events;

use Modules\Messaging\Contracts\DomainEvent;

/**
 * Ordering: an order was placed. Recorded in the checkout transaction, so it exists only if the
 * order does.
 */
class OrderPlaced implements DomainEvent
{
    public function __construct(public string $orderId) {}

    public static function eventName(): string
    {
        return 'ordering.order_placed';
    }

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return ['order_id' => $this->orderId];
    }

    public static function fromPayload(array $payload): static
    {
        return new static($payload['order_id']);
    }
}
