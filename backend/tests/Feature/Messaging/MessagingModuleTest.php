<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Modules\Messaging\Contracts\DomainEvent;
use Modules\Messaging\Contracts\EventConsumer;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Messaging\Contracts\EventSubscriptions;
use Modules\Messaging\Providers\MessagingServiceProvider;
use Tests\Unit\Architecture\Support\ModuleLayout;

it('exposes exactly four messaging contracts', function () {
    $contracts = array_map(fn (string $file) => basename($file, '.php'), glob(ModuleLayout::srcOf('Messaging').'/Contracts/*.php'));
    sort($contracts);

    expect($contracts)->toBe(['DomainEvent', 'EventConsumer', 'EventOutbox', 'EventSubscriptions']);

    foreach ([DomainEvent::class, EventConsumer::class, EventOutbox::class, EventSubscriptions::class] as $contract) {
        expect(interface_exists($contract))->toBeTrue();
    }
});

it('registers the five event commands from its own provider', function () {
    $commands = array_keys(Artisan::all());

    foreach (['outbox:relay', 'events:transport', 'events:consume', 'events:failed', 'events:retry'] as $command) {
        expect($commands)->toContain($command);
    }

    // The provider of the module owns the wiring; the application provider keeps none of it.
    expect(app()->getProvider(MessagingServiceProvider::class))->not->toBeNull();

    $application = new AppServiceProvider(app());
    $wired = [
        ...array_keys(property_exists($application, 'bindings') ? $application->bindings : []),
        ...array_keys(property_exists($application, 'singletons') ? $application->singletons : []),
    ];

    foreach ([EventOutbox::class, EventSubscriptions::class] as $contract) {
        expect($wired)->not->toContain($contract);
    }

    expect(file_get_contents((new ReflectionClass(AppServiceProvider::class))->getFileName()))
        ->not->toContain('RelayOutboxCommand', 'EventTransportSelector', 'EventTransport::class', 'EventOutbox', 'EventSubscription', 'OutboxMessageRepository');
});
