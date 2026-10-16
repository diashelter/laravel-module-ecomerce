<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging;

use App\Modules\Shared\Http\Middleware\AssignRequestId;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Context;
use UnexpectedValueException;

/**
 * One message for one consumer: a failing consumer is retried alone, without repeating the others.
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
     *                                  version: the delivery fails instead of guessing
     */
    public function handle(EventSubscriptions $subscriptions, Container $container): void
    {
        $event = $subscriptions->eventOf($this->envelope->name);

        if ($event === null || $event::eventVersion() !== $this->envelope->version) {
            throw new UnexpectedValueException("No subscribed event reads {$this->envelope->name} version {$this->envelope->version}.");
        }

        // The relay that queued this job has no request: the consumer logs, and the events it
        // records, carry the request_id of the request that recorded this message.
        if ($this->envelope->requestId === null) {
            Context::forget(AssignRequestId::CONTEXT_KEY);
        } else {
            Context::add(AssignRequestId::CONTEXT_KEY, $this->envelope->requestId);
        }

        $container->make($this->consumer)->handle($event::fromPayload($this->envelope->payload));
    }

    /** What `queue:failed` lists: the event and the consumer. */
    public function displayName(): string
    {
        return "{$this->envelope->name} -> {$this->consumer}";
    }
}
