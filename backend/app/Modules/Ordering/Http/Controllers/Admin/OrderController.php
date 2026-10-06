<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Controllers\Admin;

use App\Modules\Ordering\Http\Resources\OrderResource;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read only: order status is changed exclusively by the events/listeners/jobs.
 */
class OrderController extends Controller
{
    public function index(OrderRepository $orders): AnonymousResourceCollection
    {
        return OrderResource::collection($orders->paginateForAdmin(15));
    }

    public function show(Order $order): OrderResource
    {
        return OrderResource::make($order->load(['customer', 'items']));
    }
}
