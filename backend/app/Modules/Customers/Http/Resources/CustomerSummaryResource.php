<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Account fields (see CustomerProfileResource) plus the purchase history.
 *
 * @property CustomerSummaryDTO $resource
 */
class CustomerSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            ...CustomerProfileResource::make($summary->account)->resolve($request),
            'orders_count' => $summary->ordersCount,
            'orders' => $this->when(
                $summary->recentOrders !== null,
                fn () => OrderSummaryResource::collection(iterator_to_array($summary->recentOrders)),
            ),
        ];
    }
}
