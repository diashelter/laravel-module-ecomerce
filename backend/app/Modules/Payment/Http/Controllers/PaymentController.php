<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Ordering\Http\Resources\OrderResource;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\UseCases\PayOrderUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Fake payment: no gateway, approving simply fires the PaymentApproved event (see PayOrderUseCase).
 */
class PaymentController extends Controller
{
    public function store(Order $order, PayOrderUseCase $payOrder): JsonResponse
    {
        Gate::authorize('pay', $order);

        $order = $payOrder->execute($order);

        // 202 Accepted: the status change happens asynchronously in the queue.
        return OrderResource::make($order)
            ->additional(['message' => 'Pagamento aprovado. O pedido será atualizado em instantes.'])
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
