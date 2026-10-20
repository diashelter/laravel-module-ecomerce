<?php

declare(strict_types=1);

namespace Modules\Ordering\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ordering\Core\ValueObjects\ValidatedCart;
use Modules\Ordering\Core\ValueObjects\ValidatedCartLine;

/** @property ValidatedCart $resource */
class ValidatedCartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'items' => array_map(fn (ValidatedCartLine $line) => [
                'product_id' => $line->productId,
                'name' => $line->name,
                'image_url' => $line->imageUrl,
                'unit_price_cents' => $line->unitPriceCents,
                'quantity' => $line->quantity,
                'subtotal_cents' => $line->subtotalCents(),
                'available_quantity' => $line->availableQuantity,
                'is_available' => $line->isAvailable,
                'problem' => $line->problem,
            ], iterator_to_array($this->resource)),
            'total_cents' => $this->resource->totalCents(),
            'is_valid' => $this->resource->isValid(),
        ];
    }
}
