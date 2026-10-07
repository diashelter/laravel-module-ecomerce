<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Ordering\Http\Resources\OrderResource;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Http\Requests\PayOrderRequest;
use App\Modules\Payment\UseCases\PayOrderUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Pays an order through the payment gateway (see PayOrderUseCase). A decline is rendered as 402.
 */
class PaymentController extends Controller
{
    public function store(PayOrderRequest $request, Order $order, PayOrderUseCase $payOrder): JsonResponse
    {
        Gate::authorize('pay', $order);

        $order = $payOrder->execute($order, $request->toDto());

        // 202 Accepted: the status change happens asynchronously in the queue.
        return OrderResource::make($order)
            ->additional(['message' => 'Pagamento aprovado. O pedido será atualizado em instantes.'])
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
