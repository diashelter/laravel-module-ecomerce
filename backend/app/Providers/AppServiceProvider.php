<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Shared\Console\RelayOutboxCommand;
use App\Modules\Shared\Messaging\EventOutbox;
use App\Modules\Shared\Messaging\EventSubscriptions;
use App\Modules\Shared\Messaging\EventTransport;
use App\Modules\Shared\Messaging\QueueEventTransport;
use App\Modules\Shared\Repositories\OutboxMessageRepository;
use App\Modules\Shared\Support\RecordId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EventOutbox::class => OutboxMessageRepository::class,
        // The one transport today. HEL-13 adds another behind a feature flag, here.
        EventTransport::class => QueueEventTransport::class,
    ];

    /** @var array<class-string, class-string> the modules subscribe their consumers into the same instance */
    public array $singletons = [
        EventSubscriptions::class => EventSubscriptions::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Fails fast in development when a relationship is lazy loaded (N+1 queries).
        Model::preventLazyLoading(! $this->app->isProduction());

        // Route parameters are canonical UUIDs: "/api/products/abc" returns 404 before any query,
        // instead of a database error (PostgreSQL refuses a non-UUID compared with a `uuid` column).
        foreach (['product', 'category', 'stock', 'user', 'customer', 'order', 'address'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }

        $this->commands([RelayOutboxCommand::class]);
    }
}
