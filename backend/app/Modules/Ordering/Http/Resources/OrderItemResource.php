<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Resources;

use App\Modules\Ordering\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'unit_price' => $this->unit_price,
            'quantity' => $this->quantity,
            'subtotal' => $this->subtotal,
        ];
    }
}
