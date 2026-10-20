<?php

declare(strict_types=1);

namespace Modules\Ordering\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Ordering\Core\UseCases\ValidateCartUseCase;
use Modules\Ordering\Http\Requests\ValidateCartRequest;
use Modules\Ordering\Http\Resources\ValidatedCartResource;
use Modules\Shared\Http\Controllers\Controller;

class CartController extends Controller
{
    /**
     * Re-validates the cart (existence, status, stock, quantity and current price).
     */
    public function validate(ValidateCartRequest $request, ValidateCartUseCase $validateCart): JsonResponse
    {
        return (new ValidatedCartResource($validateCart->execute($request->toDto())))->response();
    }
}
