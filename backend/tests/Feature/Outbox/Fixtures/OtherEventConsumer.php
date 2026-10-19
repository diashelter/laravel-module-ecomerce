<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\EventConsumer;

/**
 * A consumer of OtherEvent: its queue is bound to another event name than the ones of SampleEvent.
 */
final class OtherEventConsumer implements EventConsumer
{
    /** @var list<OtherEvent> */
    public array $calls = [];

    public function handle(OtherEvent $event): void
    {
        $this->calls[] = $event;
    }
}
