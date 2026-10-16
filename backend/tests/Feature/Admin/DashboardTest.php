<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Models\Order;

it('returns aggregated metrics', function () {
    productWithStock(10);
    productWithStock(0);
    Product::factory()->inactive()->withStock(5)->create();
    customer();
    Order::factory()->create(['created_at' => now()]);
    Order::factory()->create(['created_at' => now()->subMonths(3)]);

    $response = $this->actingAs(admin())->getJson('/api/admin/dashboard')->assertOk();

    $response->assertJsonPath('data.cards', [
        'total_products' => 3,
        'active_products' => 2,
        'inactive_products' => 1,
        'total_stock_units' => 15,
        // 1 explicit customer + 2 created by the order factory.
        'total_customers' => 3,
        'total_orders' => 2,
    ]);
    $response->assertJsonPath('data.stock', ['products_in_stock' => 2, 'products_out_of_stock' => 1]);

    expect($response->json('data.orders_per_day'))->toHaveCount(30)
        ->and(collect($response->json('data.orders_per_day'))->sum('total'))->toBe(1)
        ->and($response->json('data.orders_per_month'))->toHaveCount(12)
        ->and(collect($response->json('data.orders_per_month'))->sum('total'))->toBe(2);
});

it('returns zeros for an empty store', function () {
    $response = $this->actingAs(admin())->getJson('/api/admin/dashboard')->assertOk();

    $response->assertJsonPath('data.cards', [
        'total_products' => 0,
        'active_products' => 0,
        'inactive_products' => 0,
        'total_stock_units' => 0,
        'total_customers' => 0,
        'total_orders' => 0,
    ]);
    $response->assertJsonPath('data.stock', ['products_in_stock' => 0, 'products_out_of_stock' => 0]);

    expect($response->json('data.orders_per_day'))->toHaveCount(30)
        ->and(collect($response->json('data.orders_per_day'))->sum('total'))->toBe(0)
        ->and($response->json('data.orders_per_month'))->toHaveCount(12)
        ->and(collect($response->json('data.orders_per_month'))->sum('total'))->toBe(0);
});
