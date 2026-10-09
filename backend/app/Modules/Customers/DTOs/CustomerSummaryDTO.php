<?php

declare(strict_types=1);

namespace App\Modules\Customers\DTOs;

use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Ordering\ValueObjects\OrderSummaries;

/**
 * Backoffice "Clientes" screen: the account (identity) next to its purchase history (ordering).
 * The two sides are read separately and only meet here.
 */
final readonly class CustomerSummaryDTO
{
    /**
     * @param  OrderSummaries|null  $recentOrders  null when the screen does not show them (listing)
     */
    public function __construct(
        public CustomerProfile $account,
        public int $ordersCount,
        public ?OrderSummaries $recentOrders = null,
    ) {}
}
