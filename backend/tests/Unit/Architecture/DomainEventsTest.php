<?php

use App\Modules\Shared\Messaging\DomainEvent;

/*
 * Every event goes through the outbox (EventOutbox::record, in the transaction of the change it
 * announces): a module event is a DomainEvent, and nothing in the modules dispatches an event through
 * the Laravel dispatcher. One namespace and one target per expectation.
 */

$modulesWithEvents = array_map(
    fn (string $directory) => basename(dirname($directory)),
    glob(__DIR__.'/../../../app/Modules/*/Events', GLOB_ONLYDIR),
);

foreach ($modulesWithEvents as $module) {
    arch("implements DomainEvent in every module event: {$module}")
        ->expect("App\\Modules\\{$module}\\Events")
        ->toImplement(DomainEvent::class);
}

it('implements DomainEvent in every module event: every module with events is covered', function () use ($modulesWithEvents) {
    expect($modulesWithEvents)->toBe(['Fulfillment', 'Ordering', 'Payment']);
});

foreach ([
    'Illuminate\Foundation\Events\Dispatchable',
    'Illuminate\Contracts\Events\ShouldDispatchAfterCommit',
    'Illuminate\Support\Facades\Event',
    'event',
] as $dispatching) {
    arch("uses no laravel event dispatching in the modules: {$dispatching}")
        ->expect('App\Modules')
        ->not->toUse($dispatching);
}
