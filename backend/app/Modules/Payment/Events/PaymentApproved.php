<?php

declare(strict_types=1);

namespace App\Modules\Payment\Events;

use App\Modules\Shared\Messaging\DomainEvent;

/**
 * Payment: the (fake) payment of an order was approved. Ordering reacts by marking the order
 * as paid; payment itself never changes the order status.
 */
class PaymentApproved implements DomainEvent
{
    public function __construct(public string $orderId) {}

    public static function eventName(): string
    {
        return 'payment.payment_approved';
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
