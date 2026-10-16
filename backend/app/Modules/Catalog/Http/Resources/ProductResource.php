<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\ValueObjects\ProductCategory;
use App\Modules\Catalog\ValueObjects\ProductView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property ProductView $resource */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->resource->product;
        $stock = $this->resource->stock;
        // Availability is always decided by the backend (active AND stock > 0).
        $availability = $this->resource->availability();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'price_cents' => $product->priceCents,
            'description' => $product->description,
            'image_url' => $product->imageUrl,
            'status' => $product->status->value,
            'status_label' => $product->status->label(),
            'is_available' => $availability->isAvailable(),
            'available_quantity' => $availability->availableQuantity(),
            'stock' => $stock === null ? null : [
                'id' => $stock->id,
                'quantity' => $stock->quantity,
            ],
            'categories' => array_map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ], iterator_to_array($this->resource->categories)),
            'created_at' => $product->createdAt?->toIso8601String(),
            'updated_at' => $product->updatedAt?->toIso8601String(),
        ];
    }
}
