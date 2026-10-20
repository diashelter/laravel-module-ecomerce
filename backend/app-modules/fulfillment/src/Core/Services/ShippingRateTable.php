<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Core\Services;

use Modules\Ordering\Contracts\ShippingQuoter;
use Modules\Ordering\Core\Enums\BrazilianState;
use Modules\Ordering\Core\ValueObjects\ShippingQuote;

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
