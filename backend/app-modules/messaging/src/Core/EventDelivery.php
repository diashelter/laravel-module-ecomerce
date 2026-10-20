<?php

declare(strict_types=1);

namespace Modules\Messaging\Core;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Context;
use Modules\Messaging\Contracts\EventConsumer;
use Modules\Shared\Http\Middleware\AssignRequestId;
use UnexpectedValueException;

/**
 * The rules of one delivery, the same whichever transport brought the envelope: the Laravel queue
 * (DeliverEvent) or RabbitMQ (events:consume). Nothing here knows how the envelope arrived.
 */
final class EventDelivery
{
    public function __construct(
        private readonly EventSubscriptionRegistry $subscriptions,
        private readonly Container $container,
    ) {}

    /**
     * @param  class-string<EventConsumer>  $consumer
     *
     * @throws UnexpectedValueException when the subscribed event does not declare the envelope
     *                                  version: the delivery fails instead of guessing
     */
    public function deliver(EventEnvelope $envelope, string $consumer): void
    {
        $event = $this->subscriptions->eventOf($envelope->name);

        if ($event === null || $event::eventVersion() !== $envelope->version) {
            throw new UnexpectedValueException("No subscribed event reads {$envelope->name} version {$envelope->version}.");
        }

        // The relay that published the message has no request: the consumer logs, and the events it
        // records, carry the request_id of the request that recorded this message.
        if ($envelope->requestId === null) {
            Context::forget(AssignRequestId::CONTEXT_KEY);
        } else {
            Context::add(AssignRequestId::CONTEXT_KEY, $envelope->requestId);
        }

        $this->container->make($consumer)->handle($event::fromPayload($envelope->payload));
    }
}
