<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;
use InterNACHI\Modular\Support\Cache;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The module library caches the module list in bootstrap/cache/app-modules.php and has no setting for the
        // path. Without the cache every request scans the app-modules folders (about 38 ms on a bind mount), so the
        // path follows APP_MODULES_CACHE, like the framework's APP_CONFIG_CACHE: the load stack caches the modules
        // inside its container instead of in the tree that the development API also reads.
        $this->app->singleton(Cache::class, fn (Application $app) => new Cache(
            $app->make(Filesystem::class),
            Env::get('APP_MODULES_CACHE') ?: $app->bootstrapPath('cache/app-modules.php'),
        ));
    }

    public function boot(): void
    {
        // Fails fast in development when a relationship is lazy loaded (N+1 queries).
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
