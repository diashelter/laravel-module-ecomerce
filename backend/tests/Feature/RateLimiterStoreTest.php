<?php

use Illuminate\Cache\RateLimiter;

// Only the load test stack sets CACHE_LIMITER (load-tests/docker-compose.yml). Everywhere else the
// rate limiter keeps its counts in the default store, so the limits of the routes stay real.
it('leaves the rate limiter on the default store without CACHE_LIMITER', function () {
    expect(config('cache.limiter'))->toBeNull();

    $store = (fn () => $this->cache)->call(app(RateLimiter::class));

    expect($store->getStore())->toBe(app('cache')->store()->getStore());
});
