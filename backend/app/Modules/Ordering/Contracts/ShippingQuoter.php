<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Contracts;

use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\ValueObjects\ShippingQuote;

/**
 * What the ordering side needs from the shipping rules, without depending on the fulfillment
 * module: the price and the delivery time for a state. Implemented by the fulfillment module.
 */
interface ShippingQuoter
{
    public function quote(BrazilianState $state): ShippingQuote;
}
