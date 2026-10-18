<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\EventConsumer;
use RuntimeException;

/**
 * Another consumer of SampleEvent that always throws, so two dead queues can hold a delivery of the same message.
 */
final class SecondFailingConsumer implements EventConsumer
{
    public int $attempts = 0;

    public function handle(SampleEvent $event): void
    {
        $this->attempts++;

        throw new RuntimeException('second consumer down');
    }
}
