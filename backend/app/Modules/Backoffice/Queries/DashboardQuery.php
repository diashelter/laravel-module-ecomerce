<?php

declare(strict_types=1);

namespace App\Modules\Backoffice\Queries;

use App\Modules\Backoffice\Services\DashboardService;
use App\Modules\Catalog\Contracts\CatalogMetrics;
use App\Modules\Identity\Contracts\CustomerMetrics;
use App\Modules\Inventory\Contracts\StockMetrics;
use App\Modules\Ordering\Contracts\OrderMetrics;
use Illuminate\Support\Carbon;

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
