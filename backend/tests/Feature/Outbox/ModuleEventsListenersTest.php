<?php

use Illuminate\Support\Facades\Event;
use Tests\Unit\Architecture\Support\ModuleLayout;

/*
 * The events of the business modules travel through the outbox only (moved from the messaging module's
 * EventDeliveryTest: it reads the events of every module, so it belongs to the composition suite).
 */
it('registers no laravel listener for the module events', function () {
    $events = array_map(
        ModuleLayout::classOf(...),
        ModuleLayout::filesMatching('Events/*.php'),
    );

    expect($events)->toHaveCount(5);

    foreach ($events as $event) {
        expect(class_exists($event))->toBeTrue()
            ->and(Event::hasListeners($event))->toBeFalse("{$event} has a Laravel listener");
    }
});
