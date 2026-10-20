<?php

use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\Shared\Support\RecordId;
use Tests\Unit\Architecture\Support\ModuleLayout;

/*
 * Each module owns its API routes: app-modules/<module>/routes/api.php, wrapped in the /api prefix and the
 * api middleware group because the module library only requires the file. There is no central routes file.
 */

it('has no central api routes file', function () {
    $files = array_map('basename', glob(base_path('routes/*.php')) ?: []);

    expect($files)->toBe(['console.php'])
        ->and(file_get_contents(base_path('bootstrap/app.php')))->not->toMatch('/\bapi\s*:/');
});

it('declares the routes of the module in its own api file', function (string $module) {
    $file = base_path('app-modules/'.Str::kebab($module).'/routes/api.php');

    expect(is_file($file))->toBeTrue()
        ->and(file_get_contents($file))->toContain("Route::prefix('api')->middleware('api')->group(");
})->with(ModuleLayout::BUSINESS);

it('keeps shared and messaging without routes', function (string $module) {
    expect(is_dir(base_path('app-modules/'.Str::kebab($module).'/routes')))->toBeFalse();
})->with(ModuleLayout::INFRASTRUCTURE);

it('uses only the controllers of its own module in its routes file', function (string $module) {
    $file = base_path('app-modules/'.Str::kebab($module).'/routes/api.php');

    expect(ModuleLayout::routeViolations($file, $module))->toBe([]);
})->with(ModuleLayout::BUSINESS);

/*
 * The rule above has a planted violation: the detector must report it, otherwise the rule on the real
 * tree is passing because it looks at nothing.
 */
it('fails on a deliberate route violation', function () {
    $fixture = __DIR__.'/Fixtures/Routes/foreign-controller/routes/api.php';

    expect(ModuleLayout::routeViolations($fixture, 'Ordering'))->toBe(['Modules\Catalog\Http\Controllers\ProductController']);
});

it('repeats no method and uri among the routes', function () {
    $seen = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $seen[] = $method.' '.$route->uri();
        }
    }

    expect($seen)->not->toBeEmpty()
        ->and(array_keys(array_filter(array_count_values($seen), fn (int $count) => $count > 1)))->toBe([]);
});

/*
 * A route copies the global patterns when it is created, and the module library creates the routes of a
 * module before the boot() of any provider: the pattern is registered in the register() of the module that
 * owns the parameter, never in a boot().
 */
it('registers the route parameter pattern in the register method of its owner', function (string $parameter, string $owner) {
    $provider = "Modules\\{$owner}\\Providers\\{$owner}ServiceProvider";
    $original = Route::getFacadeRoot();
    $fresh = new Router(new Dispatcher(app()), app());

    Route::swap($fresh);

    try {
        /** @var ServiceProvider $instance */
        $instance = new $provider(app());
        $instance->register();
    } finally {
        Route::swap($original);
    }

    expect($fresh->getPatterns())->toHaveKey($parameter)
        ->and($fresh->getPatterns()[$parameter])->toBe(RecordId::PATTERN);
})->with([
    'product' => ['product', 'Catalog'],
    'category' => ['category', 'Catalog'],
    'stock' => ['stock', 'Inventory'],
    'user' => ['user', 'Identity'],
    'customer' => ['customer', 'Customers'],
    'address' => ['address', 'Customers'],
    'order' => ['order', 'Ordering'],
]);

it('keeps Route pattern out of the app service provider', function () {
    expect(file_get_contents(app_path('Providers/AppServiceProvider.php')))->not->toContain('Route::pattern');
});
