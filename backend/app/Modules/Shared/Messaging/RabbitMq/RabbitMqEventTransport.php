<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging\RabbitMq;

use App\Modules\Shared\Messaging\EventEnvelope;
use App\Modules\Shared\Messaging\EventTransport;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

/**
 * The event transport over RabbitMQ: one persistent message per event on the `domain_events`
 * exchange, routed by the event name; the broker puts a copy in the queue of each consumer that
 * subscribes to it. publish() returns only after the broker confirmed the message, and throws when
 * it refused it, did not answer within 5 seconds or could not be reached, so the outbox row stays
 * pending.
 *
 * It keeps one connection open for the life of the process and declares the topology on the first
 * publication of each connection; a failure drops the connection, and the next publication opens a
 * new one.
 */
final class RabbitMqEventTransport implements EventTransport
{
    public const CONFIRMATION_TIMEOUT_SECONDS = 5;

    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly RabbitMqConnector $connector,
        private readonly RabbitMqTopology $topology,
    ) {}

    public function publish(EventEnvelope $envelope): void
    {
        try {
            $channel = $this->channel();
            $channel->basic_publish(RabbitMqEnvelope::toMessage($envelope), RabbitMqTopology::EXCHANGE, $envelope->name);
            $channel->wait_for_pending_acks(self::CONFIRMATION_TIMEOUT_SECONDS);
        } catch (Throwable $e) {
            $this->disconnect();

            throw $e;
        }
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->connection?->isConnected()) {
            return $this->channel;
        }

        $this->disconnect();
        $this->connection = $this->connector->connect();
        $channel = $this->connection->channel();
        $channel->confirm_select();
        $channel->set_nack_handler(function (AMQPMessage $message): void {
            throw new RuntimeException('RabbitMQ refused the message (negative acknowledgement from the broker).');
        });
        $this->topology->declare($channel);

        return $this->channel = $channel;
    }

    private function disconnect(): void
    {
        try {
            $this->connection?->close();
        } catch (Throwable) {
            // The connection is already gone.
        }

        $this->connection = null;
        $this->channel = null;
    }
}
