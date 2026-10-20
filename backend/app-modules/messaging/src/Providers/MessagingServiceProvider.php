<?php

declare(strict_types=1);

namespace Modules\Messaging\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Messaging\Console\ConsumeEventsCommand;
use Modules\Messaging\Console\EventTransportCommand;
use Modules\Messaging\Console\FailedEventsCommand;
use Modules\Messaging\Console\RelayOutboxCommand;
use Modules\Messaging\Console\RetryEventsCommand;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Messaging\Contracts\EventSubscriptions;
use Modules\Messaging\Core\EventSubscriptionRegistry;
use Modules\Messaging\Core\EventTransport;
use Modules\Messaging\Core\EventTransportFlag;
use Modules\Messaging\Core\EventTransportSelector;
use Modules\Messaging\Core\RabbitMq\RabbitMqEventTransport;
use Modules\Messaging\Persistence\Repositories\OutboxMessageRepository;

/**
 * Wires the event layer: the outbox, the subscription registry the modules subscribe their consumers
 * into, the transport the event-transport flag names, and the commands that run them.
 */
class MessagingServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EventOutbox::class => OutboxMessageRepository::class,
        // The transport is the one the event-transport flag names (redis or rabbitmq), chosen on every publish.
        EventTransport::class => EventTransportSelector::class,
    ];

    /** @var array<class-string, class-string> the modules subscribe their consumers into the same instance */
    public array $singletons = [
        EventSubscriptionRegistry::class => EventSubscriptionRegistry::class,
        // One connection to the broker per process.
        RabbitMqEventTransport::class => RabbitMqEventTransport::class,
        // It remembers the last transport it used, to log a change once.
        EventTransportSelector::class => EventTransportSelector::class,
    ];

    public function register(): void
    {
        // The modules see the registry only through the contract; the module itself reads the concrete one.
        $this->app->alias(EventSubscriptionRegistry::class, EventSubscriptions::class);
    }

    public function boot(): void
    {
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
