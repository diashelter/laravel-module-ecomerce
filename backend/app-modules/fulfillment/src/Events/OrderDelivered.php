<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Events;

use Modules\Messaging\Contracts\DomainEvent;

/**
 * Fulfillment: the (fake) carrier delivered the order. Ordering reacts by closing the order.
 */
class OrderDelivered implements DomainEvent
{
    public function __construct(public string $orderId) {}

    public static function eventName(): string
    {
        return 'fulfillment.order_delivered';
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
