<?php

declare(strict_types=1);

namespace Modules\Messaging\Core;

use Throwable;

/**
 * The port the outbox relay publishes through. Use cases, events and consumers never know which
 * transport is behind it: the container hands out the EventTransportSelector, which picks the Laravel queue
 * (QueueEventTransport) or RabbitMQ (RabbitMqEventTransport) by the event-transport flag.
 */
interface EventTransport
{
    /**
     * Returning means the transport accepted the envelope and the message can be marked published.
     *
     * @throws Throwable when the transport did not accept it: the message stays pending
     */
    public function publish(EventEnvelope $envelope): void;
}
