<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customers\Http\Resources\CustomerProfileResource;
use Modules\Customers\Http\Resources\OrderSummaryResource;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Ordering\Contracts\CustomerOrderHistory;
use Modules\Shared\Http\Controllers\Controller;

class AccountController extends Controller
{
    private const RECENT_ORDERS_LIMIT = 5;

    public function show(Request $request, CustomerAccounts $accounts, CustomerOrderHistory $orders): JsonResponse
    {
        $customer = $accounts->findProfile($request->user('customer')->getAuthIdentifier());
        $recentOrders = $orders->recentForCustomer($customer->id, self::RECENT_ORDERS_LIMIT);

        return response()->json([
            'data' => [
                'customer' => CustomerProfileResource::make($customer),
                'orders_count' => $orders->countForCustomer($customer->id),
                'last_order' => $recentOrders->first() === null ? null : OrderSummaryResource::make($recentOrders->first()),
                'recent_orders' => OrderSummaryResource::collection(iterator_to_array($recentOrders)),
            ],
        ]);
    }
}
