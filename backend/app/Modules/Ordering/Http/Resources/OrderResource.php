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
            // The items are what is left of the total once the shipping is taken out.
            'items_total_cents' => $this->total_cents - $this->shipping_cents,
            'shipping_cents' => $this->shipping_cents,
            'total_cents' => $this->total_cents,
            'delivery' => $this->delivery(),
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
     * Where the order goes and when it is expected: the address copy the order kept, the quoted
     * business days and the date set after the payment approval (null before it).
     *
     * @return array{business_days: int, estimated_on: string|null, address: array<string, string|null>}
     */
    private function delivery(): array
    {
        return [
            'business_days' => $this->delivery_business_days,
            'estimated_on' => $this->estimated_delivery_on?->toDateString(),
            'address' => [
                'recipient_name' => $this->delivery_recipient_name,
                'postal_code' => $this->delivery_postal_code,
                'street' => $this->delivery_street,
                'number' => $this->delivery_number,
                'complement' => $this->delivery_complement,
                'district' => $this->delivery_district,
                'city' => $this->delivery_city,
                'state' => $this->delivery_state->value,
            ],
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
