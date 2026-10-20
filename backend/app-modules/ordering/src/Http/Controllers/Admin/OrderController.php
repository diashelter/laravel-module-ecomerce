<?php

declare(strict_types=1);

namespace Modules\Ordering\Http\Controllers\Admin;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Ordering\Http\Resources\OrderResource;
use Modules\Ordering\Persistence\Repositories\OrderRepository;
use Modules\Shared\Http\Controllers\Controller;

/**
 * Read only: order status is changed exclusively by the events/listeners/jobs.
 */
class OrderController extends Controller
{
    public function index(OrderRepository $orders): AnonymousResourceCollection
    {
        return OrderResource::collection($orders->paginateForAdmin(15));
    }

    public function show(string $order, OrderRepository $orders): OrderResource
    {
        return OrderResource::make($orders->findWithCustomerAndItems($order));
    }
}
