<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Resources;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total' => $this->total,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_step' => $this->status->step(),
            'timeline' => $this->timeline(),
            'items_count' => $this->whenCounted('items'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The four lifecycle steps, marking those already reached by the order.
     *
     * @return list<array{status: string, label: string, step: int, completed: bool}>
     */
    private function timeline(): array
    {
        return array_map(fn (OrderStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'step' => $status->step(),
            'completed' => $status->step() <= $this->status->step(),
        ], OrderStatus::cases());
    }
}
