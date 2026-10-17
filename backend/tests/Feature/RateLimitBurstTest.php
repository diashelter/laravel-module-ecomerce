<?php

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Runs $request in $size forked processes released at the same instant and returns what each one got (childOutcome()).
 * The children share the booted application with the test and open their own Redis connections.
 *
 * @param  Closure(): TestResponse  $request
 * @return list<array{status: int, exception: string|null}>
 */
function simultaneousOutcomes(int $size, Closure $request): array
{
    [$reader, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $startAt = microtime(true) + 1;
    $children = [];

    foreach (range(1, $size) as $child) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            try {
                // One short line per child: a write this small reaches the socket whole, never mixed with another's.
                fwrite($writer, childOutcome($startAt, $request).PHP_EOL);
            } finally {
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
    $lines = array_filter(explode(PHP_EOL, (string) stream_get_contents($reader)));
    fclose($reader);

    return array_map(fn (string $line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), array_values($lines));
}

/**
 * What a child of simultaneousOutcomes() reports, as a line of JSON: the status it got and, when the application
 * answered with a server error or the request threw (status 0), the exception, so a failed run says why.
 *
 * @param  Closure(): TestResponse  $request
 */
function childOutcome(float $startAt, Closure $request): string
{
    try {
        while (microtime(true) < $startAt) {
            usleep(100);
        }

        $response = $request();
        $status = $response->status();
        $exception = $status >= 500 ? $response->exception : null;
    } catch (Throwable $thrown) {
        [$status, $exception] = [0, $thrown];
    }

    return json_encode([
        'status' => $status,
        'exception' => $exception === null ? null : Str::limit(sprintf(
            '%s: %s (%s:%d)', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine(),
        ), 1000),
    ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
}

/** The array store keeps the counts of each process apart: these tests count in Redis, as in production. */
beforeEach(function () {
    config(['cache.limiter' => 'redis']);
    $this->app->forgetInstance(RateLimiter::class);
});

/**
 * With the framework's throttle, all 30 passed: each read the count before any of them added to it.
 * A counter of its own per run; its keys expire with the one-minute window.
 *
 * Under /api, like every throttled route, the 429 is the JSON of ApiExceptionRenderer. Outside it, the 429 was the
 * framework's HTML page, and with no compiled Blade view yet (a new checkout, or after view:clear) every refused child
 * compiled it at once: on the bind mount of Docker Desktop, a file that another child was renaming over could be
 * missing for an instant, and some children got a 500 instead of the 429.
 */
it('lets exactly the limit through when 30 requests arrive at the same instant', function () {
    Route::post('api/burst-probe', fn () => response()->noContent())
        ->middleware('throttle:10,1,burst-'.Str::lower(Str::random(12)));

    $outcomes = simultaneousOutcomes(30, fn () => $this->post('api/burst-probe'));
    $statuses = array_count_values(array_column($outcomes, 'status'));
    ksort($statuses);

    expect($statuses)->toBe([204 => 10, 429 => 20], implode(PHP_EOL, array_filter(array_column($outcomes, 'exception'))));
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
