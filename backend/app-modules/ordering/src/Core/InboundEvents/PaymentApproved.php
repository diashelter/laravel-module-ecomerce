<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\InboundEvents;

use Modules\Messaging\Contracts\DomainEvent;

/**
 * How the ordering module reads payment.payment_approved, published by the payment module: the published
 * format is the name, the version and the payload keys, never the producer's class, so the ordering module
 * names nothing of the payment module. Only the consumers and the provider use it: the payment module
 * records the event, never the ordering one.
 */
final class PaymentApproved implements DomainEvent
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
        return new self($payload['order_id']);
    }
}
