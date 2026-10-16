<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\EventConsumer;
use Illuminate\Support\Facades\Context;

/**
 * Keeps every event it receives and the Context request_id at the moment of the call. Bind the
 * instance in the container so the delivery resolves the same one.
 */
class SpyConsumer implements EventConsumer
{
    /** @var list<array{event: SampleEvent, request_id: mixed}> */
    public array $calls = [];

    public function handle(SampleEvent $event): void
    {
        $this->calls[] = ['event' => $event, 'request_id' => Context::get('request_id')];
    }
}
