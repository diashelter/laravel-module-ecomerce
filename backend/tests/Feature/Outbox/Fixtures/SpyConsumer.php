<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use Closure;
use Illuminate\Support\Facades\Context;
use Modules\Messaging\Contracts\EventConsumer;

/**
 * Keeps every event it receives and the Context request_id at the moment of the call. Bind the
 * instance in the container so the delivery resolves the same one.
 */
class SpyConsumer implements EventConsumer
{
    /** @var list<array{event: SampleEvent, request_id: mixed}> */
    public array $calls = [];

    /** Runs with each event while the delivery is in progress, to look at the broker or change the world. */
    public ?Closure $during = null;

    public function handle(SampleEvent $event): void
    {
        $this->calls[] = ['event' => $event, 'request_id' => Context::get('request_id')];

        if ($this->during !== null) {
            ($this->during)($event);
        }
    }
}
