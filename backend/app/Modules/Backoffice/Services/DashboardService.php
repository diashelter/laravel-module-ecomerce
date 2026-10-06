<?php

declare(strict_types=1);

namespace App\Modules\Backoffice\Services;

use App\Modules\Catalog\Enums\ProductStatus;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
     * @param  Collection<string, int>  $productsByStatus  [status => total]
     * @param  Collection<string, int>  $ordersPerDay  ['YYYY-MM-DD' => total] since dailySeriesStart()
     * @param  Collection<string, int>  $ordersPerMonth  ['YYYY-MM' => total] since monthlySeriesStart()
     * @return array<string, mixed>
     */
    public function build(
        Collection $productsByStatus,
        int $productsInStock,
        int $totalStockUnits,
        int $totalCustomers,
        int $totalOrders,
        Collection $ordersPerDay,
        Collection $ordersPerMonth,
        Carbon $today,
    ): array {
        $totalProducts = (int) $productsByStatus->sum();

        return [
            'cards' => [
                'total_products' => $totalProducts,
                'active_products' => (int) ($productsByStatus[ProductStatus::Active->value] ?? 0),
                'inactive_products' => (int) ($productsByStatus[ProductStatus::Inactive->value] ?? 0),
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
     * @param  Collection<string, int>  $counts
     * @return list<array{date: string, label: string, total: int}>
     */
    private function ordersPerDay(Collection $counts, Carbon $today): array
    {
        // Days without orders are filled with zero so the chart has no gaps.
        return collect(CarbonPeriod::create($this->dailySeriesStart($today), '1 day', $today->copy()->startOfDay()))
            ->map(fn (Carbon $day) => [
                'date' => $day->toDateString(),
                'label' => $day->format('d/m'),
                'total' => (int) ($counts[$day->toDateString()] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, int>  $counts
     * @return list<array{month: string, label: string, total: int}>
     */
    private function ordersPerMonth(Collection $counts, Carbon $today): array
    {
        return collect(CarbonPeriod::create($this->monthlySeriesStart($today), '1 month', $today->copy()->startOfMonth()))
            ->map(fn (Carbon $month) => [
                'month' => $month->format('Y-m'),
                'label' => $month->format('m/Y'),
                'total' => (int) ($counts[$month->format('Y-m')] ?? 0),
            ])
            ->values()
            ->all();
    }
}
