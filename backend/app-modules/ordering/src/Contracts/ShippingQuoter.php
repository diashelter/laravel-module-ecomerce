<?php

declare(strict_types=1);

namespace Modules\Ordering\Contracts;

use Modules\Ordering\Core\Enums\BrazilianState;
use Modules\Ordering\Core\ValueObjects\ShippingQuote;

/**
 * What the ordering side needs from the shipping rules, without depending on the fulfillment
 * module: the price and the delivery time for a state. Implemented by the fulfillment module.
 */
interface ShippingQuoter
{
    public function quote(BrazilianState $state): ShippingQuote;
}
