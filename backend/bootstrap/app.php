<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Identity\Http\Middleware\EnsureUserIsAdmin;
use Modules\Shared\Exceptions\ApiExceptionRenderer;
use Modules\Shared\Http\Middleware\AssignRequestId;
use Modules\Shared\Http\Middleware\AtomicThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // First global middleware: every response (errors included) gets an X-Request-ID.
        $middleware->prepend(AssignRequestId::class);

        // Sanctum SPA: requests coming from the frontend domain use session cookies + CSRF.
        $middleware->statefulApi();

        // No trustProxies(): Nginx talks FastCGI, not HTTP, and passes the client's address as REMOTE_ADDR.
        // Trusting a proxy here would let any client pick its IP (and its rate limit key) with X-Forwarded-For.

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            // The framework's throttle checks the count before adding to it: a burst would pass the limit.
            'throttle' => AtomicThrottleRequests::class,
        ]);

        // This is an API: never redirect guests, just answer 401.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (Throwable $e, Request $request) => (new ApiExceptionRenderer)($e, $request));
    })->create();
