<?php

use App\Modules\Identity\Http\Middleware\EnsureUserIsAdmin;
use App\Modules\Shared\Exceptions\ApiExceptionRenderer;
use App\Modules\Shared\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Listeners live inside each module (app/Modules/<Module>/Listeners).
    ->withEvents(discover: [
        __DIR__.'/../app/Modules/*/Listeners',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // First global middleware: every response (errors included) gets an X-Request-ID.
        $middleware->prepend(AssignRequestId::class);

        // Sanctum SPA: requests coming from the frontend domain use session cookies + CSRF.
        $middleware->statefulApi();

        // Requests arrive through the Nginx container.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
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
