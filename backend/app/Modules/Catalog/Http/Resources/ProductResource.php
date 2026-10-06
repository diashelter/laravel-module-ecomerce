<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Services\PurchaseAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availability = app(PurchaseAvailabilityService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'price_cents' => $this->price_cents,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // Availability is always decided by the backend (active AND stock > 0).
            'is_available' => $this->whenLoaded('stock', fn () => $availability->isAvailable($this->resource, $this->stock)),
            'available_quantity' => $this->whenLoaded('stock', fn () => $availability->availableQuantity($this->stock)),
            'stock' => $this->whenLoaded('stock', fn () => $this->stock ? [
                'id' => $this->stock->id,
                'quantity' => $this->stock->quantity,
            ] : null),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
