<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures;

use Modules\Messaging\Contracts\DomainEvent;

/**
 * A reader of payment.payment_approved that reads the order id under the wrong key: the compatibility
 * check between a reader and its producer must catch it.
 */
final class DisagreeingReader implements DomainEvent
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
        return ['orderId' => $this->orderId];
    }

    public static function fromPayload(array $payload): static
    {
        return new self((string) ($payload['orderId'] ?? ''));
    }
}
