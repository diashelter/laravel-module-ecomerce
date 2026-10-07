<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Controllers;

use App\Modules\Ordering\Http\Requests\StoreOrderRequest;
use App\Modules\Ordering\Http\Resources\OrderResource;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Ordering\UseCases\PlaceOrderUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request, OrderRepository $orders): AnonymousResourceCollection
    {
        return OrderResource::collection($orders->paginateForCustomer($request->user('customer')->id, 10));
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return OrderResource::make($order->load('items'));
    }

    public function store(StoreOrderRequest $request, PlaceOrderUseCase $placeOrder): JsonResponse
    {
        $order = $placeOrder->execute($request->user('customer')->id, $request->toDto());

        return OrderResource::make($order)
            ->additional(['message' => 'Pedido criado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
