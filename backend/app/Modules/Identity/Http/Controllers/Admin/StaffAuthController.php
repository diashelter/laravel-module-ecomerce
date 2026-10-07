<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Modules\Identity\Http\Controllers\Concerns\EndsBrowserSession;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Resources\StaffMemberResource;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Admin authentication (`staff` guard), against `users`. Same cookie session as the store, but
 * a different guard: store credentials do not open the admin and vice versa.
 */
class StaffAuthController extends Controller
{
    use EndsBrowserSession;

    public function login(LoginRequest $request): StaffMemberResource
    {
        if (! Auth::guard('staff')->attempt($request->toDto()->toArray())) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou senha inválidos.'],
            ]);
        }

        // Prevents session fixation.
        $request->session()->regenerate();

        return StaffMemberResource::make($request->user('staff'));
    }

    /**
     * The browser has a single session, so logging out ends both areas.
     */
    public function logout(Request $request): Response
    {
        return $this->endBrowserSession($request);
    }

    public function me(Request $request): StaffMemberResource
    {
        return StaffMemberResource::make($request->user('staff'));
    }
}
