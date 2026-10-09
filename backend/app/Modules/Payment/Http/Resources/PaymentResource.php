<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Resources;

use App\Modules\Payment\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One payment attempt, without the card token nor the gateway details.
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status->value,
            'amount_cents' => $this->amount_cents,
        ];
    }
}
