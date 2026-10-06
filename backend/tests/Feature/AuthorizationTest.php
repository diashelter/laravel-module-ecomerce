<?php

use App\Modules\Ordering\Models\Order;

dataset('admin endpoints', [
    ['get', '/api/admin/dashboard'],
    ['get', '/api/admin/products'],
    ['post', '/api/admin/products'],
    ['get', '/api/admin/categories'],
    ['get', '/api/admin/stocks'],
    ['get', '/api/admin/users'],
    ['post', '/api/admin/users'],
    ['get', '/api/admin/orders'],
]);

it('requires authentication for admin endpoints', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with('admin endpoints');

it('forbids customers from admin endpoints', function (string $method, string $uri) {
    $response = $this->actingAs(customer())->json($method, $uri)->assertForbidden();

    assertApiError($response, 'FORBIDDEN', 'Você não tem permissão para realizar esta ação.');
})->with('admin endpoints');

it('allows admins on admin endpoints', function () {
    $this->actingAs(admin())->getJson('/api/admin/dashboard')->assertOk();
});

it('forbids a customer from seeing another customer order', function () {
    $order = Order::factory()->create();

    $this->actingAs(customer())->getJson("/api/orders/{$order->id}")->assertForbidden();
});

it('forbids admins from placing orders', function () {
    $product = productWithStock(5);

    $this->actingAs(admin())
        ->postJson('/api/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
        ->assertForbidden();
});

it('returns 404 with the standard error shape for unknown resources', function () {
    assertApiError($this->getJson('/api/products/999999')->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');

    $this->getJson('/api/products/abc')->assertNotFound();
});
