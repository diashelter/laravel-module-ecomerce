<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Ordering\ValueObjects\OrderForPayment;
use App\Modules\Payment\Http\Requests\PayOrderRequest;
use App\Modules\Payment\Http\Resources\PaymentResource;
use App\Modules\Payment\UseCases\PayOrderUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Pays an order through the payment gateway (see PayOrderUseCase). A decline is rendered as 402.
 * The order arrives as data, resolved through the ordering module's PayableOrders contract
 * (see PaymentServiceProvider), never as a model.
 */
class PaymentController extends Controller
{
    public function store(PayOrderRequest $request, OrderForPayment $payableOrder, PayOrderUseCase $payOrder): JsonResponse
    {
        Gate::authorize('pay', $payableOrder);

        $payment = $payOrder->execute($payableOrder, $request->toDto());

        // 202 Accepted: the status change happens asynchronously in the queue.
        return PaymentResource::make($payment)
            ->additional(['message' => 'Pagamento aprovado. O pedido será atualizado em instantes.'])
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
