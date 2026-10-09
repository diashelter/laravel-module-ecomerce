<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Resources;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Services\PurchaseAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stock */
class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'image_url' => $this->product->image_url,
                'status' => $this->product->status->value,
                'status_label' => $this->product->status->label(),
                'is_available' => app(PurchaseAvailabilityService::class)->isAvailable($this->product->isActive(), $this->quantity),
            ]),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
