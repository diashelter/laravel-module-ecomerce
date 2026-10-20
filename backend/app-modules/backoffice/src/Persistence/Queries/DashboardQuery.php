<?php

declare(strict_types=1);

namespace Modules\Backoffice\Persistence\Queries;

use Illuminate\Support\Carbon;
use Modules\Backoffice\Core\Services\DashboardService;
use Modules\Catalog\Contracts\CatalogMetrics;
use Modules\Identity\Contracts\CustomerMetrics;
use Modules\Inventory\Contracts\StockMetrics;
use Modules\Ordering\Contracts\OrderMetrics;

/**
 * Admin: reads the store metrics and builds the dashboard. It only reads, and it reads each number
 * from the module that owns it, through that module's metrics contract.
 */
class DashboardQuery
{
    public function __construct(
        private readonly CatalogMetrics $products,
        private readonly StockMetrics $stock,
        private readonly CustomerMetrics $customers,
        private readonly OrderMetrics $orders,
        private readonly DashboardService $dashboard,
    ) {}

    /** @return array<string, mixed> the body of the dashboard response */
    public function get(): array
    {
        $today = Carbon::today();
        $stock = $this->stock->totals();

        return $this->dashboard->build(
            productsByStatus: $this->products->productCountsByStatus(),
            productsInStock: $stock->productsInStock,
            totalStockUnits: $stock->totalUnits,
            totalCustomers: $this->customers->totalCustomers(),
            totalOrders: $this->orders->totalOrders(),
            ordersPerDay: $this->orders->countPerDaySince($this->dashboard->dailySeriesStart($today)),
            ordersPerMonth: $this->orders->countPerMonthSince($this->dashboard->monthlySeriesStart($today)),
            today: $today,
        );
    }
}
