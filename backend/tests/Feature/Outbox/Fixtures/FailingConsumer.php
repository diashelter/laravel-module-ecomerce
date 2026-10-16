<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\EventConsumer;
use RuntimeException;

/**
 * Always throws, and counts how many times it was called. Bind the instance in the container.
 */
final class FailingConsumer implements EventConsumer
{
    public int $attempts = 0;

    public function handle(SampleEvent $event): void
    {
        $this->attempts++;

        throw new RuntimeException('consumer down');
    }
}
