<?php

use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;

beforeEach(fn () => $this->actingAs(admin()));

it('lists all orders with their customers', function () {
    Order::factory()->count(3)->create();

    $this->getJson('/api/admin/orders')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'total', 'status', 'status_label', 'customer' => ['name'], 'created_at']]]);
});

it('shows an order with customer and items', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->count(2)->for($order)->create();

    $this->getJson("/api/admin/orders/{$order->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonCount(4, 'data.timeline')
        ->assertJsonPath('data.customer.id', $order->user_id);
});
