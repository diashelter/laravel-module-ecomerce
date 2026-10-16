<?php

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Runs $request in $size forked processes released at the same instant and returns the status each one got.
 * The children share the booted application with the test and open their own Redis connections.
 *
 * @param  Closure(): int  $request
 * @return list<int>
 */
function simultaneousStatuses(int $size, Closure $request): array
{
    [$reader, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $startAt = microtime(true) + 1;
    $children = [];

    foreach (range(1, $size) as $child) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            $status = 0;

            try {
                while (microtime(true) < $startAt) {
                    usleep(100);
                }

                $status = $request();
            } finally {
                fwrite($writer, pack('n', $status));

                // SIGKILL skips the shutdown functions and destructors of the test runner, which belong to the
                // parent, and leaves the database connection inherited from it untouched.
                posix_kill(posix_getpid(), SIGKILL);
            }
        }

        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $exitStatus);
    }

    fclose($writer);
    $statuses = array_values(unpack('n*', stream_get_contents($reader)) ?: []);
    fclose($reader);

    return $statuses;
}

/** The array store keeps the counts of each process apart: these tests count in Redis, as in production. */
beforeEach(function () {
    config(['cache.limiter' => 'redis']);
    $this->app->forgetInstance(RateLimiter::class);
});

/**
 * With the framework's throttle, all 30 passed: each read the count before any of them added to it.
 * A counter of its own per run; its keys expire with the one-minute window.
 */
it('lets exactly the limit through when 30 requests arrive at the same instant', function () {
    Route::post('burst-probe', fn () => response()->noContent())
        ->middleware('throttle:10,1,burst-'.Str::lower(Str::random(12)));

    $statuses = array_count_values(simultaneousStatuses(30, fn () => $this->post('burst-probe')->status()));
    ksort($statuses);

    expect($statuses)->toBe([204 => 10, 429 => 20]);
});

/** The framework's increment gives no expiry to a counter that already exists: it would refuse forever. */
it('gives the window expiry to a counter left without one', function () {
    $store = Cache::store('redis')->getStore();
    $key = 'stale-'.Str::lower(Str::random(12));
    $store->connection()->set($store->getPrefix().$key, 10);

    expect(app(RateLimiter::class)->hit($key, 60))->toBe(11)
        ->and($store->connection()->ttl($store->getPrefix().$key))->toBe(60);

    app(RateLimiter::class)->clear($key);
});
