<?php

declare(strict_types=1);

namespace Modules\Ordering\Persistence\Queries;

use Carbon\CarbonInterface;
use Modules\Ordering\Contracts\OrderMetrics;
use Modules\Ordering\Core\ValueObjects\OrderCountsByPeriod;
use Modules\Ordering\Persistence\Models\Order;

/**
 * Counts the orders for the admin dashboard.
 */
class OrderMetricsQuery implements OrderMetrics
{
    public function totalOrders(): int
    {
        return Order::query()->count();
    }

    public function countPerDaySince(CarbonInterface $start): OrderCountsByPeriod
    {
        return new OrderCountsByPeriod(Order::query()
            ->where('created_at', '>=', $start)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day'));
    }

    public function countPerMonthSince(CarbonInterface $start): OrderCountsByPeriod
    {
        return new OrderCountsByPeriod(Order::query()
            ->where('created_at', '>=', $start)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month'));
    }
}
