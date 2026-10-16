<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Events;

use App\Modules\Shared\Messaging\DomainEvent;

/**
 * Ordering: the order was marked as paid. Recorded only once, in the transaction of the transition
 * that actually moved the order to "payment_approved", so fulfillment never starts for an unpaid
 * order. It carries the business days quoted at checkout, so fulfillment schedules the delivery
 * without reading the order.
 */
class OrderPaid implements DomainEvent
{
    public function __construct(public string $orderId, public int $deliveryBusinessDays) {}

    public static function eventName(): string
    {
        return 'ordering.order_paid';
    }

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return ['order_id' => $this->orderId, 'delivery_business_days' => $this->deliveryBusinessDays];
    }

    public static function fromPayload(array $payload): static
    {
        return new static($payload['order_id'], $payload['delivery_business_days']);
    }
}
