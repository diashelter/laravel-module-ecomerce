<?php

declare(strict_types=1);

namespace App\Modules\Customers\DTOs;

use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Ordering\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * Backoffice "Clientes" screen: the account (identity) next to its purchase history (ordering).
 * The two sides are read separately and only meet here.
 */
final readonly class CustomerSummaryDTO
{
    /**
     * @param  Collection<int, Order>|null  $recentOrders  null when the screen does not show them (listing)
     */
    public function __construct(
        public CustomerAccount $account,
        public int $ordersCount,
        public ?Collection $recentOrders = null,
    ) {}
}
