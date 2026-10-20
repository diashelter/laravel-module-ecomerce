<?php

use Modules\Ordering\Persistence\Models\Order;
use Modules\Ordering\Persistence\Models\OrderItem;

beforeEach(fn () => $this->actingAs(admin()));

it('lists all orders with their customers', function () {
    Order::factory()->count(3)->create();

    $this->getJson('/api/admin/orders')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'total_cents', 'status', 'status_label', 'customer' => ['name'], 'created_at']]]);
});

it('shows an order with customer and items', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->count(2)->for($order)->create();

    $this->getJson("/api/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonCount(4, 'data.timeline')
        ->assertJsonPath('data.customer.id', $order->customer_id);
});

it('returns the order code with every order', function (string $area, string $uri, string $path) {
    $account = customer();
    $order = Order::factory()->for($account, 'customer')->create();
    $this->actingAs($area === 'customer' ? $account : admin());

    $this->getJson(str_replace('{order}', $order->id, $uri))->assertOk()->assertJsonPath($path, $order->code);
})->with([
    'customer list' => ['customer', '/api/orders', 'data.0.code'],
    'customer order' => ['customer', '/api/orders/{order}', 'data.code'],
    'admin list' => ['staff', '/api/admin/orders', 'data.0.code'],
    'admin order' => ['staff', '/api/admin/orders/{order}', 'data.code'],
]);
