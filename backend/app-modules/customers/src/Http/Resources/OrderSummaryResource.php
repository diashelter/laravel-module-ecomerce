<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ordering\Core\ValueObjects\OrderSummary;

/**
 * One order of the customer's history, as the account screens list it.
 *
 * @property OrderSummary $resource
 */
class OrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'code' => $this->resource->code,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'total_cents' => $this->resource->totalCents,
            'created_at' => $this->resource->createdAt->toIso8601String(),
        ];
    }
}
