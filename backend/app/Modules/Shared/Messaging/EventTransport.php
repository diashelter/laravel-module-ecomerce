<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging;

use Throwable;

/**
 * The port the outbox relay publishes through. Use cases, events and consumers never know which
 * transport is behind it (today the Laravel queue, see QueueEventTransport).
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
