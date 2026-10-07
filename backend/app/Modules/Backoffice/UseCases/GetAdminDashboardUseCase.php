<?php

declare(strict_types=1);

namespace App\Modules\Backoffice\UseCases;

use App\Modules\Backoffice\Services\DashboardService;
use App\Modules\Catalog\Repositories\ProductRepository;
use App\Modules\Identity\Repositories\CustomerAccountRepository;
use App\Modules\Inventory\Repositories\StockRepository;
use App\Modules\Ordering\Repositories\OrderRepository;
use Illuminate\Support\Carbon;

/**
 * Admin: reads the store metrics and builds the dashboard.
 */
final class GetAdminDashboardUseCase
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly StockRepository $stocks,
        private readonly CustomerAccountRepository $customers,
        private readonly OrderRepository $orders,
        private readonly DashboardService $dashboard,
    ) {}

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $today = Carbon::today();

        return $this->dashboard->build(
            productsByStatus: $this->products->countByStatus(),
            productsInStock: $this->stocks->countInStock(),
            totalStockUnits: $this->stocks->totalUnits(),
            totalCustomers: $this->customers->count(),
            totalOrders: $this->orders->count(),
            ordersPerDay: $this->orders->countPerDaySince($this->dashboard->dailySeriesStart($today)),
            ordersPerMonth: $this->orders->countPerMonthSince($this->dashboard->monthlySeriesStart($today)),
            today: $today,
        );
    }
}
