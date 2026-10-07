<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

/**
 * What it costs and how long it takes to deliver to a state: the price in cents and the
 * delivery time in business days. Calculated by the fulfillment module (see ShippingQuoter).
 */
final readonly class ShippingQuote
{
    public function __construct(
        public int $priceCents,
        public int $deliveryBusinessDays,
    ) {}
}
