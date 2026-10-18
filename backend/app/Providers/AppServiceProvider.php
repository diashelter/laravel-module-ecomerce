<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Shared\Cache\AtomicRateLimiter;
use App\Modules\Shared\Console\ConsumeEventsCommand;
use App\Modules\Shared\Console\EventTransportCommand;
use App\Modules\Shared\Console\FailedEventsCommand;
use App\Modules\Shared\Console\RelayOutboxCommand;
use App\Modules\Shared\Console\RetryEventsCommand;
use App\Modules\Shared\Messaging\EventOutbox;
use App\Modules\Shared\Messaging\EventSubscriptions;
use App\Modules\Shared\Messaging\EventTransport;
use App\Modules\Shared\Messaging\EventTransportFlag;
use App\Modules\Shared\Messaging\EventTransportSelector;
use App\Modules\Shared\Messaging\RabbitMq\RabbitMqEventTransport;
use App\Modules\Shared\Repositories\OutboxMessageRepository;
use App\Modules\Shared\Support\RecordId;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EventOutbox::class => OutboxMessageRepository::class,
        // The transport is the one the event-transport flag names (redis or rabbitmq), chosen on every publish.
        EventTransport::class => EventTransportSelector::class,
    ];

    /** @var array<class-string, class-string> the modules subscribe their consumers into the same instance */
    public array $singletons = [
        EventSubscriptions::class => EventSubscriptions::class,
        // One connection to the broker per process.
        RabbitMqEventTransport::class => RabbitMqEventTransport::class,
        // It remembers the last transport it used, to log a change once.
        EventTransportSelector::class => EventTransportSelector::class,
    ];

    public function register(): void
    {
        // The framework registers its RateLimiter in a deferred provider, which would replace a plain binding
        // when it loads: the extension swaps the instance it builds, on the same store (cache.limiter).
        $this->app->extend(RateLimiter::class, fn (RateLimiter $limiter, Application $app) => new AtomicRateLimiter(
            $app->make('cache')->driver($app['config']->get('cache.limiter')),
        ));
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

        $this->app->make(EventTransportFlag::class)->define();

        $this->commands([
            RelayOutboxCommand::class,
            EventTransportCommand::class,
            ConsumeEventsCommand::class,
            FailedEventsCommand::class,
            RetryEventsCommand::class,
        ]);
    }
}
