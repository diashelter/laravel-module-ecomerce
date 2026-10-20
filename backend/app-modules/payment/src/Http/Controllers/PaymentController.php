<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Payment\Core\UseCases\PayOrderUseCase;
use Modules\Payment\Http\Requests\PayOrderRequest;
use Modules\Payment\Http\Resources\PaymentResource;
use Modules\Shared\Http\Controllers\Controller;

/**
 * Pays an order through the payment gateway (see PayOrderUseCase). A decline is rendered as 402.
 * The route only carries the order id: the use case finds the order among the customer's own,
 * through the ordering module's PayableOrders contract.
 */
class PaymentController extends Controller
{
    public function store(PayOrderRequest $request, string $order, PayOrderUseCase $payOrder): JsonResponse
    {
        $payment = $payOrder->execute($request->user('customer')->getAuthIdentifier(), $order, $request->toDto());

        // 202 Accepted: the status change happens asynchronously in the queue.
        return PaymentResource::make($payment)
            ->additional(['message' => 'Pagamento aprovado. O pedido será atualizado em instantes.'])
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
