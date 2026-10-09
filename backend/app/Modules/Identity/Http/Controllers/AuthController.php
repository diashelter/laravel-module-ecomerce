<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Controllers\Concerns\EndsBrowserSession;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\CustomerProfileResource;
use App\Modules\Identity\UseCases\RegisterCustomerUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Store authentication (`customer` guard). Sanctum SPA: the session lives in an HTTP-only
 * cookie, no token is ever handed to the frontend.
 */
class AuthController extends Controller
{
    use EndsBrowserSession;

    public function register(RegisterRequest $request, RegisterCustomerUseCase $registerCustomer): JsonResponse
    {
        $account = $registerCustomer->execute($request->toDto());

        Auth::guard('customer')->login($account);
        $request->session()->regenerate();

        return CustomerProfileResource::make($account->toProfile())->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): CustomerProfileResource
    {
        if (! Auth::guard('customer')->attempt($request->toDto()->toArray())) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou senha inválidos.'],
            ]);
        }

        // Prevents session fixation.
        $request->session()->regenerate();

        return CustomerProfileResource::make($request->user('customer')->toProfile());
    }

    /**
     * The browser has a single session, so logging out ends both areas.
     */
    public function logout(Request $request): Response
    {
        return $this->endBrowserSession($request);
    }

    public function me(Request $request): CustomerProfileResource
    {
        return CustomerProfileResource::make($request->user('customer')->toProfile());
    }
}
