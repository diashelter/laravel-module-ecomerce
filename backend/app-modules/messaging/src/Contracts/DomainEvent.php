<?php

declare(strict_types=1);

namespace Modules\Messaging\Contracts;

/**
 * An event a module publishes. It is stored in the outbox and read back outside PHP (HEL-13), so it
 * declares its own stable name and version and maps itself to a payload of scalars: neither the
 * PHP class nor its properties are the published format.
 *
 * The name is "<module>.<event in snake_case>" (ordering.order_paid) and the payload keys are
 * snake_case. Changing the payload means a new version.
 */
interface DomainEvent
{
    public static function eventName(): string;

    public static function eventVersion(): int;

    /** @return array<string, string|int|float|bool|null> */
    public function toPayload(): array;

    /** @param  array<string, string|int|float|bool|null>  $payload */
    public static function fromPayload(array $payload): static;
}
