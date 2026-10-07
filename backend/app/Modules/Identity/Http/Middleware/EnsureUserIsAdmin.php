<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff-only gate for what only the `admin` role may do: every DELETE of the admin area and the
 * staff management. Runs after `auth:staff`.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('staff')?->isAdmin(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
