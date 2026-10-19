<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use UnexpectedValueException;

/**
 * One message for one consumer on the Laravel queue: a failing consumer is retried alone, without
 * repeating the others. The rules of the delivery are EventDelivery's.
 * It carries the whole envelope, so it still runs after the outbox row was pruned (a queue:retry
 * days later).
 */
final class DeliverEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public EventEnvelope $envelope, public string $consumer) {}

    /**
     * @throws UnexpectedValueException when the subscribed event does not declare the envelope
     *                                  version (see EventDelivery)
     */
    public function handle(EventDelivery $delivery): void
    {
        $delivery->deliver($this->envelope, $this->consumer);
    }

    /** What `queue:failed` lists: the event and the consumer. */
    public function displayName(): string
    {
        return "{$this->envelope->name} -> {$this->consumer}";
    }
}
