<?php

declare(strict_types=1);

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Contracts\CustomerMetrics;
use Modules\Identity\Persistence\Queries\CustomerMetricsQuery;
use Modules\Identity\Persistence\Repositories\CustomerAccountRepository;
use Modules\Shared\Support\RecordId;

/**
 * Wires the contracts identity publishes to the other modules.
 */
class IdentityServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CustomerAccounts::class => CustomerAccountRepository::class,
        CustomerMetrics::class => CustomerMetricsQuery::class,
    ];

    public function register(): void
    {
        // The route parameters this module owns are canonical UUIDs (see CatalogServiceProvider::register()).
        foreach (['user'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }
    }
}
