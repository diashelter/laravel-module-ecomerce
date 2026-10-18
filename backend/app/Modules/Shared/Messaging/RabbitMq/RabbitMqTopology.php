<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging\RabbitMq;

use App\Modules\Shared\Messaging\EventSubscriptions;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * What the broker holds for the events: the exchange the relay publishes to, and for each
 * subscribed consumer its queue, bound by the names of the events it subscribes to, and the dead
 * queue where a delivery that failed three times ends. Declaring is idempotent; the arguments of a
 * queue cannot change once it exists (a different argument is refused by the broker).
 *
 * The consumer queue is a quorum queue with `x-delivery-limit` 2: the broker counts the returns of
 * a delivery and moves it to the dead exchange when the third is due, so the three attempts of the
 * Laravel queue are the same three here.
 */
final class RabbitMqTopology
{
    public const EXCHANGE = 'domain_events';

    public const DEAD_EXCHANGE = 'domain_events.dead';

    /** Returns a delivery may suffer before the broker moves it to the dead queue: 3 deliveries in all. */
    public const DELIVERY_LIMIT = 2;

    public function __construct(private readonly EventSubscriptions $subscriptions) {}

    public static function deadQueueOf(string $consumerName): string
    {
        return "{$consumerName}.dead";
    }

    public function declare(AMQPChannel $channel): void
    {
        $channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);
        $channel->exchange_declare(self::DEAD_EXCHANGE, 'direct', false, true, false);

        foreach (array_keys($this->subscriptions->consumerClasses()) as $consumerName) {
            $channel->queue_declare($consumerName, false, true, false, false, false, new AMQPTable([
                'x-queue-type' => 'quorum',
                'x-delivery-limit' => self::DELIVERY_LIMIT,
                'x-dead-letter-exchange' => self::DEAD_EXCHANGE,
                'x-dead-letter-routing-key' => $consumerName,
                'x-dead-letter-strategy' => 'at-least-once',
                'x-overflow' => 'reject-publish',
            ]));

            foreach ($this->subscriptions->eventNamesOf($consumerName) as $eventName) {
                $channel->queue_bind($consumerName, self::EXCHANGE, $eventName);
            }

            $channel->queue_declare(self::deadQueueOf($consumerName), false, true, false, false, false, new AMQPTable([
                'x-queue-type' => 'quorum',
            ]));
            $channel->queue_bind(self::deadQueueOf($consumerName), self::DEAD_EXCHANGE, $consumerName);
        }
    }

    /** @return list<string> the consumer queues, by stable name */
    public function consumerQueues(): array
    {
        return array_keys($this->subscriptions->consumerClasses());
    }

    /** @return list<string> */
    public function deadQueues(): array
    {
        return array_map(self::deadQueueOf(...), $this->consumerQueues());
    }
}
