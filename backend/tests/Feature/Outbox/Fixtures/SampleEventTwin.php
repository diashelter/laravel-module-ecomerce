<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\DomainEvent;

/**
 * Another event class that claims the name of SampleEvent.
 */
final class SampleEventTwin implements DomainEvent
{
    public static function eventName(): string
    {
        return 'testing.sample_event';
    }

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [];
    }

    public static function fromPayload(array $payload): static
    {
        return new self;
    }
}
