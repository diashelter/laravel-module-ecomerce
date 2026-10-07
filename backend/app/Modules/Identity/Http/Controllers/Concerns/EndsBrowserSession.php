<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * The browser has a single session cookie, so logging out of either area ends both.
 */
trait EndsBrowserSession
{
    protected function endBrowserSession(Request $request): Response
    {
        Auth::guard('customer')->logout();
        Auth::guard('staff')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
