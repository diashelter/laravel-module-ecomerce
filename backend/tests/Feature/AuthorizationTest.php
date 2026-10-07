<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Models\Payment;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

dataset('admin endpoints', [
    ['get', '/api/admin/dashboard'],
    ['get', '/api/admin/products'],
    ['post', '/api/admin/products'],
    ['get', '/api/admin/categories'],
    ['get', '/api/admin/stocks'],
    ['get', '/api/admin/users'],
    ['post', '/api/admin/users'],
    ['get', '/api/admin/customers'],
    ['get', '/api/admin/orders'],
]);

dataset('store routes behind the customer login', [
    ['get', '/api/auth/me'],
    ['get', '/api/orders'],
    ['post', '/api/orders'],
    ['get', '/api/orders/{order}'],
    ['post', '/api/orders/{order}/payment'],
    ['get', '/api/account'],
    ['put', '/api/account/profile'],
    ['get', '/api/account/addresses'],
    ['post', '/api/account/addresses'],
    ['put', '/api/account/addresses/{address}'],
    ['delete', '/api/account/addresses/{address}'],
]);

/**
 * Every route under /api/admin except the login, each one with its parameters filled by
 * records that exist, so an answer can only come from the auth layers and not from a missing id.
 *
 * @return list<array{method: string, uri: string, route: RoutingRoute}>
 */
function adminRoutes(): array
{
    $ids = [
        'product' => productWithStock(1)->id,
        'category' => Category::factory()->create()->id,
        'stock' => Stock::query()->firstOrFail()->id,
        'customer' => customer()->id,
        'user' => admin()->id,
        'order' => Order::factory()->create()->id,
    ];

    $routes = [];

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/admin/') || $route->uri() === 'api/admin/auth/login') {
            continue;
        }

        $method = collect($route->methods())->first(fn (string $method) => $method !== 'HEAD');
        $uri = preg_replace_callback('/\{(\w+)\}/', fn (array $match) => (string) $ids[$match[1]], $route->uri());

        $routes[] = ['method' => $method, 'uri' => '/'.$uri, 'route' => $route];
    }

    return $routes;
}

it('requires authentication for admin endpoints', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with('admin endpoints');

it('answers 401 to a customer session on every admin route', function () {
    $routes = adminRoutes();
    $this->actingAs(customer());

    expect($routes)->toHaveCount(27);

    foreach ($routes as ['method' => $method, 'uri' => $uri]) {
        expect($this->json($method, $uri)->status())->toBe(401, "{$method} {$uri}");
    }
});

it('answers 401 to a guest on every admin route', function () {
    $routes = adminRoutes();

    expect($routes)->toHaveCount(27);

    foreach ($routes as ['method' => $method, 'uri' => $uri]) {
        expect($this->json($method, $uri)->status())->toBe(401, "{$method} {$uri}");
    }
});

it('answers 401 to a staff session on every store route', function (string $method, string $uri) {
    $order = Order::factory()->create();
    $address = addressOf(customer());
    $statusBefore = $order->status;
    $uri = str_replace(['{order}', '{address}'], [(string) $order->id, (string) $address->id], $uri);

    $this->actingAs(admin())->json($method, $uri)->assertUnauthorized();

    expect(Order::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(0)
        ->and(CustomerAddress::query()->count())->toBe(1)
        ->and($order->fresh()->status)->toBe($statusBefore);
})->with('store routes behind the customer login');

it('allows admins on admin endpoints', function () {
    $this->actingAs(admin())->getJson('/api/admin/dashboard')->assertOk();
});

it('forbids a customer from seeing another customer order', function () {
    $order = Order::factory()->create();

    $this->actingAs(customer())->getJson("/api/orders/{$order->id}")->assertForbidden();
});

it('returns 404 with the standard error shape for unknown resources', function () {
    assertApiError($this->getJson('/api/products/999999')->assertNotFound(), 'NOT_FOUND', 'Recurso não encontrado.');

    $this->getJson('/api/products/abc')->assertNotFound();
});

it('does not use sanctum token authentication on any route', function () {
    $sanctumRoutes = collect(Route::getRoutes())->filter(fn (RoutingRoute $route) => in_array('auth:sanctum', $route->gatherMiddleware(), true));

    expect($sanctumRoutes)->toHaveCount(0);
});
