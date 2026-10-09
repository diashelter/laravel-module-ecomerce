<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Http\Resources\OrderSummaryResource;
use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\Http\Resources\CustomerProfileResource;
use App\Modules\Ordering\Contracts\CustomerOrderHistory;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
