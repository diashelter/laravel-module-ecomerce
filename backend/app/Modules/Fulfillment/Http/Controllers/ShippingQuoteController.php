<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Http\Controllers;

use App\Modules\Fulfillment\Http\Requests\ShippingQuoteRequest;
use App\Modules\Fulfillment\Services\ShippingRateTable;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ShippingQuoteController extends Controller
{
    /**
     * Public: the checkout shows the freight before the customer confirms the purchase.
     */
    public function show(ShippingQuoteRequest $request, ShippingRateTable $rates): JsonResponse
    {
        $state = $request->toDto();
        $quote = $rates->quote($state);

        return response()->json(['data' => [
            'state' => $state->value,
            'price_cents' => $quote->priceCents,
            'delivery_business_days' => $quote->deliveryBusinessDays,
        ]]);
    }
}
