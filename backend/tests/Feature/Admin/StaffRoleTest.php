<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Models\Order;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

const NOT_ALLOWED = 'Você não tem permissão para realizar esta ação.';

it('forbids the support role from deleting a product', function () {
    $product = productWithStock(3);

    assertApiError($this->actingAs(support())->deleteJson("/api/admin/products/{$product->id}")->assertForbidden(), 'FORBIDDEN', NOT_ALLOWED);

    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue()
        ->and(Stock::query()->where('product_id', $product->id)->exists())->toBeTrue();
});

it('forbids the support role from deleting a category', function () {
    $category = Category::factory()->create();

    $this->actingAs(support())->deleteJson("/api/admin/categories/{$category->id}")->assertForbidden();

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

it('forbids every admin delete route to the support role', function () {
    $ids = [
        'product' => productWithStock(1)->id,
        'category' => Category::factory()->create()->id,
        'user' => admin()->id,
        'customer' => customer()->id,
    ];
    $this->actingAs(support());

    $deleteRoutes = collect(Route::getRoutes())->filter(
        fn (RoutingRoute $route) => in_array('DELETE', $route->methods(), true) && str_starts_with($route->uri(), 'api/admin/')
    );

    // products, categories and users at least; a new DELETE route joins the loop by itself.
    expect($deleteRoutes->count())->toBeGreaterThanOrEqual(3);

    foreach ($deleteRoutes as $route) {
        $uri = '/'.preg_replace_callback('/\{(\w+)\}/', fn (array $match) => (string) $ids[$match[1]], $route->uri());

        expect($this->deleteJson($uri)->status())->toBe(403, "DELETE {$uri}");
    }

    expect(Product::query()->count())->toBe(1)
        ->and(Category::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(2);
});

it('lets the support role create, edit and read in the admin', function (string $method, string $uri, int $status) {
    $product = productWithStock(5);
    $category = Category::factory()->create();
    $order = Order::factory()->create();
    $stock = Stock::query()->where('product_id', $product->id)->sole();

    // A new payload per call: a category name is unique, so the second role cannot reuse the first one.
    $payload = fn () => match ("{$method} {$uri}") {
        'POST /api/admin/products' => productPayload(),
        'PUT /api/admin/products/{product}' => productPayload(['stock_quantity' => 5]),
        'PATCH /api/admin/products/{product}/status' => ['status' => 'inactive'],
        'POST /api/admin/categories', 'PUT /api/admin/categories/{category}' => ['name' => 'Categoria '.Str::random(8)],
        'PUT /api/admin/stocks/{stock}' => ['operation' => 'increase', 'quantity' => 2],
        default => [],
    };
    $resolved = str_replace(
        ['{product}', '{category}', '{stock}', '{order}'],
        [$product->id, $category->id, $stock->id, $order->id],
        $uri,
    );

    $this->actingAs(admin())->json($method, $resolved, $payload())->assertStatus($status);
    $this->actingAs(support())->json($method, $resolved, $payload())->assertStatus($status);
})->with([
    ['POST', '/api/admin/products', 201],
    ['PUT', '/api/admin/products/{product}', 200],
    ['PATCH', '/api/admin/products/{product}/status', 200],
    ['POST', '/api/admin/categories', 201],
    ['PUT', '/api/admin/categories/{category}', 200],
    ['PUT', '/api/admin/stocks/{stock}', 200],
    ['GET', '/api/admin/orders', 200],
    ['GET', '/api/admin/orders/{order}', 200],
    ['GET', '/api/admin/dashboard', 200],
]);

it('answers 404 for unknown admin records', function (string $method, string $uri) {
    $this->actingAs(admin());

    $response = $this->json($method, $uri, ['name' => 'X', 'email' => 'x@example.com', 'role' => 'support'])->assertNotFound();

    expect($response->json('code'))->toBe('NOT_FOUND');
})->with([
    ['GET', '/api/admin/customers/999999'],
    ['PUT', '/api/admin/customers/999999'],
    ['GET', '/api/admin/users/999999'],
    ['PUT', '/api/admin/users/999999'],
    ['DELETE', '/api/admin/users/999999'],
    ['DELETE', '/api/admin/products/999999'],
    ['DELETE', '/api/admin/categories/999999'],
]);
