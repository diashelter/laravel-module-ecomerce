<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\DomainEvent;

/**
 * An event other than SampleEvent, to type a consumer that takes the wrong one.
 */
final class OtherEvent implements DomainEvent
{
    public static function eventName(): string
    {
        return 'testing.other_event';
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
