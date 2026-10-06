<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Ordering\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Account fields (same shape as UserResource) plus the purchase history.
 *
 * @property CustomerSummaryDTO $resource
 */
class CustomerSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            ...UserResource::make($summary->account)->resolve($request),
            'orders_count' => $summary->ordersCount,
            'orders' => $this->when(
                $summary->recentOrders !== null,
                fn () => OrderResource::collection($summary->recentOrders),
            ),
        ];
    }
}
