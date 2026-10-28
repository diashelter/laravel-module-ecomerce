<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/** A canonical UUID v7 that is in no table: what the tests send as an unknown id. */
function unknownId(): string
{
    return Str::uuid7()->toString();
}

/**
 * Signs a customer in on the `customer` guard by id alone. Outside the Identity module the authenticated
 * customer is an id, so a module test needs no account behind it. Returns the id (a new UUID v7 when none is given).
 */
function signInAsCustomer(?string $id = null): string
{
    $id ??= Str::uuid7()->toString();
    test()->actingAs(new GenericUser(['id' => $id]), 'customer');

    return $id;
}

/**
 * The SQL of every query the closure runs, in order. Sign in and create the data before calling it,
 * so only the request itself is counted.
 *
 * @return list<string>
 */
function queriesDuring(Closure $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();

    return array_column(DB::getQueryLog(), 'query');
}

/**
 * Asserts the standard API error body. The request_id must match the X-Request-ID header.
 *
 * @param  array<string, list<string>>  $errors
 */
function assertApiError(TestResponse $response, string $code, string $message, array $errors = []): TestResponse
{
    return $response->assertExactJson([
        'code' => $code,
        'message' => $message,
        'errors' => $errors,
        'request_id' => $response->headers->get('X-Request-ID'),
    ]);
}

/**
 * Commits the transaction RefreshDatabase wraps every test in, so what the test wrote is visible to the other
 * connections: the forked processes of runConcurrently() read it. The test removes what it wrote (truncate) at the end.
 */
function commitTestTransaction(): void
{
    while (DB::transactionLevel() > 0) {
        DB::commit();
    }
}

/**
 * Runs each task in a forked process, all released at the same instant, and returns the failures as text (empty
 * when every task finished). The children open their own database connections: the ones of the parent are closed
 * before the fork, because two processes must never share a connection socket. Commit the data first
 * (commitTestTransaction()).
 *
 * @param  list<Closure(): void>  $tasks
 * @return list<string>
 */
function runConcurrently(array $tasks): array
{
    DB::purge();
    DB::purge('mongodb');

    [$reader, $writer] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $startAt = microtime(true) + 0.5;
    $children = [];

    foreach ($tasks as $task) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            $failure = null;

            try {
                while (microtime(true) < $startAt) {
                    usleep(50);
                }

                // The testing manager treats the first transaction as the RefreshDatabase wrapper and ignores it; a
                // child commits for real, so it counts like in production.
                app()->instance('db.transactions', new DatabaseTransactionsManager);

                $task();
            } catch (Throwable $thrown) {
                $failure = Str::limit(sprintf('%s: %s (%s:%d)', $thrown::class, $thrown->getMessage(), $thrown->getFile(), $thrown->getLine()), 1000);
            }

            // One short line per child reaches the socket whole. SIGKILL skips the shutdown functions of the test
            // runner, which belong to the parent.
            fwrite($writer, json_encode($failure, JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL);
            posix_kill(posix_getpid(), SIGKILL);
        }

        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $exitStatus);
    }

    fclose($writer);
    $lines = array_filter(explode(PHP_EOL, (string) stream_get_contents($reader)));
    fclose($reader);

    expect($lines)->toHaveCount(count($tasks), 'A forked task died without reporting.');

    return array_values(array_filter(array_map(fn (string $line) => json_decode($line, true), $lines)));
}
