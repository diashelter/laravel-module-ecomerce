<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

/**
 * The right handle(), but not an EventConsumer.
 */
final class UnmarkedConsumer
{
    public function handle(SampleEvent $event): void {}
}
