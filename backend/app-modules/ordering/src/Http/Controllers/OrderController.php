<?php

declare(strict_types=1);

namespace Modules\Ordering\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Ordering\Core\UseCases\PlaceOrderUseCase;
use Modules\Ordering\Http\Requests\StoreOrderRequest;
use Modules\Ordering\Http\Resources\OrderResource;
use Modules\Ordering\Persistence\Repositories\OrderRepository;
use Modules\Shared\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index(Request $request, OrderRepository $orders): AnonymousResourceCollection
    {
        return OrderResource::collection($orders->paginateForCustomer($request->user('customer')->id, 10));
    }

    public function show(Request $request, string $order, OrderRepository $orders): OrderResource
    {
        $found = $orders->findForCustomer($request->user('customer')->id, $order) ?? abort(404);

        return OrderResource::make($found);
    }

    public function store(StoreOrderRequest $request, PlaceOrderUseCase $placeOrder): JsonResponse
    {
        $order = $placeOrder->execute($request->user('customer')->id, $request->toDto(), $request->addressId());

        return OrderResource::make($order)
            ->additional(['message' => 'Pedido criado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
