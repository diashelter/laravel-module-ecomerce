<?php

declare(strict_types=1);

namespace App\Modules\Shared\Cache;

use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RedisStore;

/**
 * The framework's rate limiter, with an increment that is one Redis call.
 *
 * The framework adds the counter at zero, increments it and, when the increment says 1 but the add failed,
 * writes 1 over it (the key could have expired in between). Two requests opening a window can interleave
 * there, and the write erases the other's hit. Here the increment and the expiry run in one script, so every
 * hit lands and each caller gets its own count (AtomicThrottleRequests decides on it).
 *
 * The layout of the keys does not change: the counter and its ":timer" key, read by attempts(), availableIn()
 * and clear(). Other stores keep the framework's increment: the array store (tests, and the load profiles
 * without limits) lives in one process.
 */
final class AtomicRateLimiter extends RateLimiter
{
    /** Increments the counter and, when it has no expiry (a new window), gives it the window's. */
    private const INCREMENT = <<<'LUA'
        local hits = redis.call('INCRBY', KEYS[1], ARGV[1])
        if redis.call('TTL', KEYS[1]) == -1 then
            redis.call('EXPIRE', KEYS[1], ARGV[2])
        end
        return hits
        LUA;

    public function increment($key, $decaySeconds = 60, $amount = 1): int
    {
        $store = $this->cache->getStore();

        if (! $store instanceof RedisStore) {
            return parent::increment($key, $decaySeconds, $amount);
        }

        $key = $this->cleanRateLimiterKey($key);

        $this->cache->add($key.':timer', $this->availableAt($decaySeconds), $decaySeconds);

        return (int) $store->connection()->eval(
            self::INCREMENT, 1, $store->getPrefix().$key, $amount, (int) max(1, $decaySeconds),
        );
    }
}
