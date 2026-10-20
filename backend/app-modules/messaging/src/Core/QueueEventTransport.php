<?php

declare(strict_types=1);

namespace Modules\Messaging\Core;

use Illuminate\Support\Facades\Bus;

/**
 * The event transport over the Laravel queue (Redis): one DeliverEvent per subscribed consumer, on
 * the default connection.
 */
final class QueueEventTransport implements EventTransport
{
    public function __construct(private readonly EventSubscriptionRegistry $subscriptions) {}

    public function publish(EventEnvelope $envelope): void
    {
        foreach ($this->subscriptions->consumersOf($envelope->name) as $consumer) {
            // Pushed now, even inside the relay transaction: the relay marks the message published
            // only after the queue accepted every delivery.
            Bus::dispatch((new DeliverEvent($envelope, $consumer))->beforeCommit());
        }
    }
}
