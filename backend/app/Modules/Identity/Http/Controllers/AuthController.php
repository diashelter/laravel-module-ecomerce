<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\UseCases\RegisterCustomerUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum SPA authentication: the session lives in an HTTP-only cookie,
 * no token is ever handed to the frontend.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterCustomerUseCase $registerCustomer): JsonResponse
    {
        $user = $registerCustomer->execute($request->toDto());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return UserResource::make($user)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::guard('web')->attempt($request->toDto()->toArray())) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou senha inválidos.'],
            ]);
        }

        // Prevents session fixation.
        $request->session()->regenerate();

        return UserResource::make($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
