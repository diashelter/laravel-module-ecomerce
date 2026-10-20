<?php

declare(strict_types=1);

namespace Modules\Messaging\Core\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

/**
 * The deliveries that failed three times and sit in the dead queue of their consumer: lists them and
 * returns them, each only to the queue of its own consumer. Returning publishes the delivery to the
 * consumer queue, waits for the broker's confirmation and only then removes it from the dead queue,
 * so a stop halfway leaves every delivery in one of the two queues, or in both, never in neither.
 */
class DeadDeliveries
{
    public function __construct(
        private readonly RabbitMqConnector $connector,
        private readonly RabbitMqTopology $topology,
    ) {}

    /**
     * Reads the dead queues without taking anything from them.
     *
     * @return list<array{consumer: string, event: string, message_id: string, occurred_at: string, request_id: string, deliveries: int}>
     *
     * @throws RuntimeException when the broker cannot be reached
     */
    public function all(): array
    {
        return $this->withChannel(function (AMQPChannel $channel): array {
            $rows = [];

            foreach ($this->read($channel) as $consumer => $messages) {
                foreach ($messages as $message) {
                    $rows[] = $this->describe($consumer, $message);
                    $message->reject(true);
                }
            }

            return $rows;
        });
    }

    /**
     * Returns the dead deliveries (all, or the ones with the given message id) to their consumers' queues.
     *
     * @return array<string, int> how many returned, by consumer
     *
     * @throws RuntimeException when the broker cannot be reached, or the message id has no dead delivery
     */
    public function retry(?string $messageId = null): array
    {
        return $this->withChannel(function (AMQPChannel $channel) use ($messageId): array {
            $channel->confirm_select();
            $returned = [];
            $found = false;

            foreach ($this->read($channel) as $consumer => $messages) {
                foreach ($messages as $message) {
                    $describe = $this->describe($consumer, $message);

                    if ($messageId !== null && $describe['message_id'] !== $messageId) {
                        $message->reject(true);

                        continue;
                    }

                    $found = true;
                    $this->republish($channel, $message, $consumer);
                    $message->ack();
                    $returned[$consumer] = ($returned[$consumer] ?? 0) + 1;
                }
            }

            if ($messageId !== null && ! $found) {
                throw new RuntimeException("No dead delivery has the message id {$messageId}.");
            }

            return $returned;
        });
    }

    /** Publishes to the consumer queue itself (default exchange), so no other consumer gets a copy. */
    protected function republish(AMQPChannel $channel, AMQPMessage $message, string $consumerQueue): void
    {
        $channel->basic_publish(new AMQPMessage($message->getBody(), [
            'content_type' => $message->get('content_type'),
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $message->get('message_id'),
            'type' => $message->get('type'),
        ]), '', $consumerQueue);
        $channel->wait_for_pending_acks(RabbitMqEventTransport::CONFIRMATION_TIMEOUT_SECONDS);
    }

    /**
     * Takes every message of every dead queue (unacknowledged: the caller returns or removes each one).
     *
     * @return array<string, list<AMQPMessage>> by consumer name
     */
    private function read(AMQPChannel $channel): array
    {
        $messages = [];

        foreach ($this->topology->consumerQueues() as $consumer) {
            $messages[$consumer] = [];

            while (($message = $channel->basic_get(RabbitMqTopology::deadQueueOf($consumer))) !== null) {
                $messages[$consumer][] = $message;
            }
        }

        return $messages;
    }

    /** @return array{consumer: string, event: string, message_id: string, occurred_at: string, request_id: string, deliveries: int} */
    private function describe(string $consumer, AMQPMessage $message): array
    {
        $body = json_decode($message->getBody(), true);
        $body = is_array($body) ? $body : [];

        return [
            'consumer' => $consumer,
            'event' => (string) ($message->has('type') ? $message->get('type') : ($body['name'] ?? '')),
            'message_id' => (string) ($message->has('message_id') ? $message->get('message_id') : ($body['id'] ?? '')),
            'occurred_at' => (string) ($body['occurred_at'] ?? ''),
            'request_id' => (string) ($body['request_id'] ?? ''),
            // Every dead delivery reached the delivery limit of its queue: the first delivery and its returns.
            'deliveries' => RabbitMqTopology::DELIVERY_LIMIT + 1,
        ];
    }

    /**
     * @template T
     *
     * @param  callable(AMQPChannel): T  $work
     * @return T
     */
    private function withChannel(callable $work): mixed
    {
        $connection = $this->connector->connect();

        try {
            $channel = $connection->channel();
            $this->topology->declare($channel);

            return $work($channel);
        } finally {
            $this->close($connection);
        }
    }

    private function close(AbstractConnection $connection): void
    {
        try {
            $connection->close();
        } catch (Throwable) {
            // The connection is already gone; the broker returns what was not acknowledged.
        }
    }
}
