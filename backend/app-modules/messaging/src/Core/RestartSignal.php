<?php

declare(strict_types=1);

namespace Modules\Messaging\Core;

use Illuminate\Contracts\Cache\Repository as Cache;
use Throwable;

/**
 * The `php artisan queue:restart` signal as a long-running process sees it (the outbox relay and the
 * event consumer): it remembers the value read at start, and `changed()` is true once the command
 * has written a different one. While the cache cannot be read (Redis down) the process keeps going,
 * and it adopts the signal it reads once the cache is back.
 */
final class RestartSignal
{
    /** The cache key `queue:restart` writes, the same signal the queue workers watch. */
    public const CACHE_KEY = 'illuminate:queue:restart';

    /** @var array{0: mixed}|null the value in a one-item list, or null while it was not readable */
    private ?array $baseline;

    public function __construct(private readonly Cache $cache)
    {
        $this->baseline = $this->read();
    }

    public function changed(): bool
    {
        $current = $this->read();

        if ($this->baseline === null) {
            $this->baseline = $current;

            return false;
        }

        return $current !== null && $current !== $this->baseline;
    }

    /** @return array{0: mixed}|null */
    private function read(): ?array
    {
        try {
            return [$this->cache->get(self::CACHE_KEY)];
        } catch (Throwable) {
            return null;
        }
    }
}
