<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Fulfillment\Core\Services\ShippingRateTable;
use Modules\Fulfillment\Http\Requests\ShippingQuoteRequest;
use Modules\Shared\Http\Controllers\Controller;

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
