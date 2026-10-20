<?php

declare(strict_types=1);

namespace Modules\Messaging\Core;

use Laravel\Pennant\Feature;

/**
 * The `event-transport` feature flag: which transport the outbox relay publishes through. One global
 * value, `redis` (the Laravel queue, the default when nothing is stored) or `rabbitmq`, kept in the
 * `features` table of Pennant: it is read and changed with Redis down, and the relay obeys it
 * without a restart.
 */
final class EventTransportFlag
{
    public const FEATURE = 'event-transport';

    public const REDIS = 'redis';

    public const RABBITMQ = 'rabbitmq';

    public const VALUES = [self::REDIS, self::RABBITMQ];

    /** Defines the feature: no stored value means the Laravel queue. */
    public function define(): void
    {
        Feature::define(self::FEATURE, fn () => self::REDIS);
    }

    public function current(): string
    {
        return Feature::globally()->value(self::FEATURE);
    }

    /** @param  'redis'|'rabbitmq'  $value */
    public function change(string $value): void
    {
        Feature::globally()->activate(self::FEATURE, $value);
    }

    public function accepts(string $value): bool
    {
        return in_array($value, self::VALUES, true);
    }

    /**
     * Drops the values Pennant keeps in memory, so the next read goes to the table. A long-running
     * process (the relay) calls it at the start of each cycle to see a change made elsewhere.
     */
    public function refresh(): void
    {
        Feature::flushCache();
    }
}
