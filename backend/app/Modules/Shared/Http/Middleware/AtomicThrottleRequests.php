<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `throttle` middleware: it counts the request first and decides on the count it got back.
 *
 * The framework's ThrottleRequests reads the count, compares it with the limit and only then adds the
 * request (ThrottleRequestsWithRedis too, and it ignores the answer of its own acquire()). Requests that
 * arrive together all read the count before any of them adds to it, and all of them pass: in a test with
 * 30 simultaneous requests against a limit of 10, all 30 passed. Here each request gets its own count from
 * one increment (AtomicRateLimiter), so exactly the limit passes.
 *
 * The 429 is the framework's (same exception, same headers), and a refused request still counts, which
 * does not stretch the window: it ends one window after its first hit. With more than one limit, a request
 * refused by one has already counted in the ones before it. A limit with after() counts only some
 * responses, so it can only count after the response: those requests keep the framework's order.
 */
final class AtomicThrottleRequests extends ThrottleRequests
{
    protected function handleRequest($request, Closure $next, array $limits): Response
    {
        foreach ($limits as $limit) {
            if ($limit->afterCallback) {
                return parent::handleRequest($request, $next, $limits);
            }
        }

        $remaining = [];

        foreach ($limits as $index => $limit) {
            $hits = $this->limiter->hit($limit->key, $limit->decaySeconds);

            if ($hits > $limit->maxAttempts) {
                throw $this->buildException($request, $limit->key, $limit->maxAttempts, $limit->responseCallback);
            }

            $remaining[$index] = $limit->maxAttempts - $hits;
        }

        $response = $next($request);

        foreach ($limits as $index => $limit) {
            $response = $this->addHeaders($response, $limit->maxAttempts, $remaining[$index]);
        }

        return $response;
    }
}
