<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Core\ValueObjects\StockView;

/** @property StockView $resource */
class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stock = $this->resource->stock;
        $product = $this->resource->product;

        return [
            'id' => $stock->id,
            'quantity' => $stock->quantity,
            'product' => $product === null ? null : [
                'id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->imageUrl,
                'status' => $product->status->value,
                'status_label' => $product->status->label(),
                'is_available' => $product->availability($stock->quantity)->isAvailable(),
            ],
            'updated_at' => $stock->updatedAt?->toIso8601String(),
        ];
    }
}
