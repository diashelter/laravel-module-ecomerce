<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\DomainEvent;

/**
 * The test event (testing.sample_event, version 1): its payload is whatever the test gives it.
 */
class SampleEvent implements DomainEvent
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(public array $payload = ['order_id' => '0199f0a0-0000-7000-8000-000000000001']) {}

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
        return $this->payload;
    }

    public static function fromPayload(array $payload): static
    {
        return new static($payload);
    }
}
