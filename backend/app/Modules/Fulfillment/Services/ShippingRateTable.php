<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Services;

use App\Modules\Ordering\Contracts\ShippingQuoter;
use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\ValueObjects\ShippingQuote;

/**
 * The shipping rule: one fixed price and delivery time per destination state, read from
 * `config/shop.php`. It is the fulfillment module's answer to the ordering side's ShippingQuoter.
 */
class ShippingRateTable implements ShippingQuoter
{
    public function quote(BrazilianState $state): ShippingQuote
    {
        $rate = config("shop.shipping_rates.{$state->value}");

        return new ShippingQuote($rate['price_cents'], $rate['business_days']);
    }
}
