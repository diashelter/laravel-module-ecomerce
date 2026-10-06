<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id that is returned in the X-Request-ID header and in API errors.
 * It is stored in the log Context, so every log line of the request (and of the queued
 * jobs it dispatches) carries the same "request_id".
 */
final class AssignRequestId
{
    public const HEADER = 'X-Request-ID';

    public const CONTEXT_KEY = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolve($request);

        Context::add(self::CONTEXT_KEY, $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    public static function current(): ?string
    {
        return Context::get(self::CONTEXT_KEY);
    }

    /**
     * Reuses the id sent by an upstream proxy when it is safe to log, otherwise generates one.
     */
    private function resolve(Request $request): string
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');

        return preg_match('/^[A-Za-z0-9._-]{8,128}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();
    }
}
