<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging;

use App\Modules\Shared\Messaging\RabbitMq\RabbitMqEventTransport;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * The EventTransport the container hands out: it reads the `event-transport` flag on every publish
 * and delegates to the matching transport. The relay, the use cases and the consumers never know
 * which one is behind it. One line in the log when the transport changes between two publications.
 */
final class EventTransportSelector implements EventTransport
{
    private ?string $last = null;

    public function __construct(private readonly Container $container, private readonly EventTransportFlag $flag) {}

    public function publish(EventEnvelope $envelope): void
    {
        $value = $this->flag->current();

        if ($this->last !== null && $this->last !== $value) {
            Log::info('The outbox relay changed the event transport.', ['transport' => $value, 'previous' => $this->last]);
        }

        $this->last = $value;

        $this->container->make($value === EventTransportFlag::RABBITMQ ? RabbitMqEventTransport::class : QueueEventTransport::class)
            ->publish($envelope);
    }
}
