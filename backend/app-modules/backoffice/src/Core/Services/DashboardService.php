<?php

declare(strict_types=1);

namespace Modules\Backoffice\Core\Services;

use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Core\ValueObjects\ProductCountsByStatus;
use Modules\Ordering\Core\ValueObjects\OrderCountsByPeriod;

/**
 * Builds the admin dashboard metrics from raw counts. The frontend only renders these numbers.
 */
class DashboardService
{
    private const DAILY_SERIES_DAYS = 30;

    private const MONTHLY_SERIES_MONTHS = 12;

    public function dailySeriesStart(Carbon $today): Carbon
    {
        return $today->copy()->startOfDay()->subDays(self::DAILY_SERIES_DAYS - 1);
    }

    public function monthlySeriesStart(Carbon $today): Carbon
    {
        return $today->copy()->startOfMonth()->subMonths(self::MONTHLY_SERIES_MONTHS - 1);
    }

    /**
     * The order counts are those since dailySeriesStart() (per day) and monthlySeriesStart() (per month).
     *
     * @return array<string, mixed>
     */
    public function build(
        ProductCountsByStatus $productsByStatus,
        int $productsInStock,
        int $totalStockUnits,
        int $totalCustomers,
        int $totalOrders,
        OrderCountsByPeriod $ordersPerDay,
        OrderCountsByPeriod $ordersPerMonth,
        Carbon $today,
    ): array {
        $totalProducts = $productsByStatus->total();

        return [
            'cards' => [
                'total_products' => $totalProducts,
                'active_products' => $productsByStatus->of(ProductStatus::Active),
                'inactive_products' => $productsByStatus->of(ProductStatus::Inactive),
                'total_stock_units' => $totalStockUnits,
                'total_customers' => $totalCustomers,
                'total_orders' => $totalOrders,
            ],
            'stock' => [
                'products_in_stock' => $productsInStock,
                'products_out_of_stock' => $totalProducts - $productsInStock,
            ],
            'orders_per_day' => $this->ordersPerDay($ordersPerDay, $today),
            'orders_per_month' => $this->ordersPerMonth($ordersPerMonth, $today),
        ];
    }

    /**
     * @return list<array{date: string, label: string, total: int}>
     */
    private function ordersPerDay(OrderCountsByPeriod $counts, Carbon $today): array
    {
        // Days without orders are filled with zero so the chart has no gaps.
        return collect(CarbonPeriod::create($this->dailySeriesStart($today), '1 day', $today->copy()->startOfDay()))
            ->map(fn (Carbon $day) => [
                'date' => $day->toDateString(),
                'label' => $day->format('d/m'),
                'total' => $counts->of($day->toDateString()),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{month: string, label: string, total: int}>
     */
    private function ordersPerMonth(OrderCountsByPeriod $counts, Carbon $today): array
    {
        return collect(CarbonPeriod::create($this->monthlySeriesStart($today), '1 month', $today->copy()->startOfMonth()))
            ->map(fn (Carbon $month) => [
                'month' => $month->format('Y-m'),
                'label' => $month->format('m/Y'),
                'total' => $counts->of($month->format('Y-m')),
            ])
            ->values()
            ->all();
    }
}
