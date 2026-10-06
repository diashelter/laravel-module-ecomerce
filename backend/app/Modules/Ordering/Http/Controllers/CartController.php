<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Controllers;

use App\Modules\Ordering\Http\Requests\ValidateCartRequest;
use App\Modules\Ordering\UseCases\ValidateCartUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    /**
     * Re-validates the cart (existence, status, stock, quantity and current price).
     */
    public function validate(ValidateCartRequest $request, ValidateCartUseCase $validateCart): JsonResponse
    {
        return response()->json(['data' => $validateCart->execute($request->toDto())]);
    }
}
