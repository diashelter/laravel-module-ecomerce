<?php

use App\Modules\Fulfillment\Services\ShippingRateTable;
use App\Modules\Ordering\Enums\BrazilianState;

/** The rate of every state, as the business set it: [price in cents, business days]. */
function expectedRates(): array
{
    $zones = [
        [['SP'], 1500, 2],
        [['RJ', 'MG', 'ES'], 2200, 4],
        [['PR', 'SC', 'RS'], 2500, 5],
        [['DF', 'GO', 'MT', 'MS'], 3000, 6],
        [['BA', 'SE', 'AL', 'PE', 'PB', 'RN', 'CE', 'PI', 'MA'], 3800, 8],
        [['PA', 'AP', 'AM', 'RR', 'AC', 'RO', 'TO'], 4500, 10],
    ];
    $rates = [];

    foreach ($zones as [$states, $priceCents, $businessDays]) {
        foreach ($states as $state) {
            $rates[$state] = [$priceCents, $businessDays];
        }
    }

    return $rates;
}

it('quotes every state from the shipping rate table', function (string $state, int $priceCents, int $businessDays) {
    $quote = (new ShippingRateTable)->quote(BrazilianState::from($state));

    expect($quote->priceCents)->toBe($priceCents)
        ->and($quote->deliveryBusinessDays)->toBe($businessDays);
})->with(function () {
    foreach (expectedRates() as $state => [$priceCents, $businessDays]) {
        yield $state => [$state, $priceCents, $businessDays];
    }
});

it('quotes every state from the shipping rate table with no state left out', function () {
    $configured = array_keys(config('shop.shipping_rates'));
    $states = array_map(fn (BrazilianState $state) => $state->value, BrazilianState::cases());

    expect(config('shop.shipping_rates'))->toHaveCount(27)
        ->and(expectedRates())->toHaveCount(27)
        ->and($configured)->toEqualCanonicalizing($states);
});
