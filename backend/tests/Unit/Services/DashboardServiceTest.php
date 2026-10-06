<?php

use App\Modules\Backoffice\Services\DashboardService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->service = new DashboardService;
    $this->today = Carbon::parse('2026-10-05 15:30:00');
});

it('starts the daily series 29 days ago and the monthly series 11 months ago', function () {
    expect($this->service->dailySeriesStart($this->today)->toDateString())->toBe('2026-09-06')
        ->and($this->service->monthlySeriesStart($this->today)->toDateString())->toBe('2025-11-01')
        ->and($this->today->toDateTimeString())->toBe('2026-10-05 15:30:00');
});

it('builds the cards and fills the series gaps with zero', function () {
    $summary = $this->service->build(
        productsByStatus: collect(['active' => 3, 'inactive' => 1]),
        productsInStock: 2,
        totalStockUnits: 40,
        totalCustomers: 5,
        totalOrders: 7,
        ordersPerDay: collect(['2026-10-05' => 4, '2026-09-06' => 1]),
        ordersPerMonth: collect(['2026-10' => 6]),
        today: $this->today,
    );

    expect($summary['cards'])->toBe([
        'total_products' => 4,
        'active_products' => 3,
        'inactive_products' => 1,
        'total_stock_units' => 40,
        'total_customers' => 5,
        'total_orders' => 7,
    ])
        ->and($summary['stock'])->toBe(['products_in_stock' => 2, 'products_out_of_stock' => 2])
        ->and($summary['orders_per_day'])->toHaveCount(30)
        ->and($summary['orders_per_day'][0])->toBe(['date' => '2026-09-06', 'label' => '06/09', 'total' => 1])
        ->and($summary['orders_per_day'][1]['total'])->toBe(0)
        ->and($summary['orders_per_day'][29])->toBe(['date' => '2026-10-05', 'label' => '05/10', 'total' => 4])
        ->and($summary['orders_per_month'])->toHaveCount(12)
        ->and($summary['orders_per_month'][0])->toBe(['month' => '2025-11', 'label' => '11/2025', 'total' => 0])
        ->and($summary['orders_per_month'][11])->toBe(['month' => '2026-10', 'label' => '10/2026', 'total' => 6]);
});
