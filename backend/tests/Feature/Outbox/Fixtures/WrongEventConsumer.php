<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use Modules\Messaging\Contracts\EventConsumer;

/**
 * A consumer whose handle() takes another event than the one it is subscribed to.
 */
final class WrongEventConsumer implements EventConsumer
{
    public function handle(OtherEvent $event): void {}
}
