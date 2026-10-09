<?php

use App\Modules\Ordering\Contracts\ShippingQuoter;
use App\Modules\Ordering\Enums\BrazilianState;

it('quotes the shipping for a state without a session', function () {
    $expected = ['state' => 'BA', 'price_cents' => 3800, 'delivery_business_days' => 8];

    $this->getJson('/api/shipping/quote?state=BA')
        ->assertOk()
        ->assertExactJson(['data' => $expected]);

    $this->actingAs(customer())->getJson('/api/shipping/quote?state=BA')
        ->assertOk()
        ->assertExactJson(['data' => $expected]);
});

it('normalizes the state of the quote', function () {
    $this->getJson('/api/shipping/quote?state=sp')
        ->assertOk()
        ->assertJsonPath('data.state', 'SP')
        ->assertJsonPath('data.price_cents', 1500)
        ->assertJsonPath('data.delivery_business_days', 2);
});

it('normalizes a state with spaces in the quote', function () {
    $this->getJson('/api/shipping/quote?state=%20sp%20')
        ->assertOk()
        ->assertJsonPath('data.state', 'SP');
});

it('rejects a state that is not a string in the quote', function () {
    $this->getJson('/api/shipping/quote?state[]=SP')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state');
});

it('rejects a quote without a valid state', function (string $query) {
    $this->getJson("/api/shipping/quote{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state');
})->with([
    'missing state' => [''],
    'unknown state' => ['?state=XX'],
]);

it('throttles the shipping quote after 60 requests per minute', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/shipping/quote?state=SP')->assertOk();
    }

    $this->getJson('/api/shipping/quote?state=SP')->assertStatus(429);
});

it('resolves the shipping quoter to the fulfillment rate table', function (string $state) {
    $quoter = app(ShippingQuoter::class);
    $route = $this->getJson("/api/shipping/quote?state={$state}")->assertOk()->json('data');
    $quote = $quoter->quote(BrazilianState::from($state));

    expect($quoter::class)->toStartWith('App\Modules\Fulfillment\\')
        ->and($quote->priceCents)->toBe($route['price_cents'])
        ->and($quote->deliveryBusinessDays)->toBe($route['delivery_business_days']);
})->with(array_map(fn (BrazilianState $state) => $state->value, BrazilianState::cases()));
