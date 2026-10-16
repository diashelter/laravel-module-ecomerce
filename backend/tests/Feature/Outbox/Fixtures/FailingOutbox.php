<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\DomainEvent;
use App\Modules\Shared\Messaging\EventOutbox;
use App\Modules\Shared\Repositories\OutboxMessageRepository;
use RuntimeException;

/**
 * Records through the real outbox and then throws RuntimeException('outbox down') for the chosen
 * event name: the message is written, so a test shows the rollback undoes it with the state change.
 */
final class FailingOutbox implements EventOutbox
{
    public function __construct(private readonly string $failingName) {}

    public function record(DomainEvent $event): void
    {
        app(OutboxMessageRepository::class)->record($event);

        if ($event::eventName() === $this->failingName) {
            throw new RuntimeException('outbox down');
        }
    }
}
