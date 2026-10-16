<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\EventConsumer;

/**
 * A valid consumer of SampleEventTwin.
 */
final class TwinConsumer implements EventConsumer
{
    public function handle(SampleEventTwin $event): void {}
}
