<?php

declare(strict_types=1);

namespace Modules\Shared\Providers;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Cache\AtomicRateLimiter;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The framework registers its RateLimiter in a deferred provider, which would replace a plain binding
        // when it loads: the extension swaps the instance it builds, on the same store (cache.limiter).
        $this->app->extend(RateLimiter::class, fn (RateLimiter $limiter, Application $app) => new AtomicRateLimiter(
            $app->make('cache')->driver($app['config']->get('cache.limiter')),
        ));
    }
}
