<?php

use App\Modules\Ordering\Contracts\OrderMetrics;
use App\Modules\Ordering\Models\Order;
use Illuminate\Support\Carbon;

it('counts all the orders for the dashboard', function () {
    expect(app(OrderMetrics::class)->totalOrders())->toBe(0);

    Order::factory()->count(3)->create();

    expect(app(OrderMetrics::class)->totalOrders())->toBe(3);
});

it('counts orders per day since a date', function () {
    $today = Carbon::today();
    Order::factory()->count(2)->create(['created_at' => $today->copy()->addHour()]);
    Order::factory()->create(['created_at' => $today->copy()->subDay()->addHour()]);
    Order::factory()->create(['created_at' => $today->copy()->subDays(10)]);

    $counts = app(OrderMetrics::class)->countPerDaySince($today->copy()->subDay());

    expect(iterator_to_array($counts))->toEqualCanonicalizing([
        $today->copy()->subDay()->toDateString() => 1,
        $today->toDateString() => 2,
    ])
        ->and($counts->of($today->copy()->subDays(10)->toDateString()))->toBe(0);
});

it('counts orders per month since a date', function () {
    $thisMonth = Carbon::today()->startOfMonth();
    Order::factory()->count(2)->create(['created_at' => $thisMonth->copy()->addDay()]);
    Order::factory()->create(['created_at' => $thisMonth->copy()->subMonth()->addDay()]);
    Order::factory()->create(['created_at' => $thisMonth->copy()->subMonths(5)]);

    $counts = app(OrderMetrics::class)->countPerMonthSince($thisMonth->copy()->subMonth());

    expect(iterator_to_array($counts))->toEqualCanonicalizing([
        $thisMonth->copy()->subMonth()->format('Y-m') => 1,
        $thisMonth->format('Y-m') => 2,
    ])
        ->and($counts->of($thisMonth->copy()->subMonths(5)->format('Y-m')))->toBe(0);
});

it('counts the orders created exactly at the start date', function () {
    $day = Carbon::today()->subDays(3);
    $month = Carbon::today()->startOfMonth()->subMonths(2);
    Order::factory()->create(['created_at' => $day]);
    Order::factory()->create(['created_at' => $month]);

    expect(app(OrderMetrics::class)->countPerDaySince($day)->of($day->toDateString()))->toBe(1)
        ->and(app(OrderMetrics::class)->countPerMonthSince($month)->of($month->format('Y-m')))->toBe(1);
});
