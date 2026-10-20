<?php

declare(strict_types=1);

namespace Modules\Messaging\Core;

use Carbon\CarbonImmutable;

/**
 * A recorded event as it travels: the message id, the event name and version, the payload and when
 * it occurred, plus the request_id of the request that recorded it. It is the published format, the
 * same keys as the outbox_messages columns, and never depends on the PHP class of the event.
 */
final readonly class EventEnvelope
{
    /** @param  array<string, string|int|float|bool|null>  $payload */
    public function __construct(
        public string $id,
        public string $name,
        public int $version,
        public array $payload,
        public CarbonImmutable $occurredAt,
        public ?string $requestId,
    ) {}
}
