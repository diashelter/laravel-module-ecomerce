<?php

declare(strict_types=1);

namespace Modules\Messaging\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Modules\Messaging\Core\EventDelivery;
use Modules\Messaging\Core\EventSubscriptionRegistry;
use Modules\Messaging\Core\RabbitMq\RabbitMqConnector;
use Modules\Messaging\Core\RabbitMq\RabbitMqEnvelope;
use Modules\Messaging\Core\RabbitMq\RabbitMqTopology;
use Modules\Messaging\Core\RestartSignal;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

/**
 * The consumer process for the RabbitMQ transport (the event-consumer container): one consumer per
 * queue of the topology, one delivery handled at a time and at most one unacknowledged per queue. A delivery is acknowledged after the consumer's
 * handle() returned; when it throws, the delivery is returned to the queue, and the broker moves it
 * to the dead queue after the third failure (see RabbitMqTopology). Without the broker it tries to
 * connect again every 5 seconds, and it stops after the delivery in progress when
 * `php artisan queue:restart` runs, like the queue workers and the relay.
 */
final class ConsumeEventsCommand extends Command
{
    /** How long to wait for a delivery before checking the restart signal. */
    private const WAIT_SECONDS = 1;

    private const RECONNECT_WAIT_SECONDS = 5;

    protected $signature = 'events:consume {--stop-when-empty : Exit with 0 once every consumer queue is empty (for tests)}';

    protected $description = 'Consume the RabbitMQ queues of the event consumers';

    public function handle(
        RabbitMqConnector $connector,
        RabbitMqTopology $topology,
        EventSubscriptionRegistry $subscriptions,
        EventDelivery $delivery,
        Cache $cache,
    ): int {
        $restartSignal = new RestartSignal($cache);

        while (true) {
            $connection = $this->connect($connector);

            try {
                if ($this->consume($connection, $topology, $subscriptions, $delivery, $restartSignal)) {
                    return self::SUCCESS;
                }
            } catch (AMQPExceptionInterface $e) {
                $this->components->warn("Lost the RabbitMQ connection: {$e->getMessage()}");
                Sleep::for(self::RECONNECT_WAIT_SECONDS)->seconds();
            } finally {
                $this->close($connection);
            }
        }
    }

    private function connect(RabbitMqConnector $connector): AbstractConnection
    {
        while (true) {
            try {
                return $connector->connect();
            } catch (RuntimeException $e) {
                $this->components->warn("{$e->getMessage()}. Trying again in ".self::RECONNECT_WAIT_SECONDS.' seconds.');
                Sleep::for(self::RECONNECT_WAIT_SECONDS)->seconds();
            }
        }
    }

    /**
     * Consumes until the process has to stop.
     *
     * @return bool true when the process must exit (restart signal, or every queue is empty)
     */
    private function consume(
        AbstractConnection $connection,
        RabbitMqTopology $topology,
        EventSubscriptionRegistry $subscriptions,
        EventDelivery $delivery,
        RestartSignal $restartSignal,
    ): bool {
        $channel = $connection->channel();
        // One unacknowledged delivery per consumer (queue). The deliveries are handled one at a time, but a
        // channel-wide limit would not make the broker hold the other queues' messages back: quorum queues
        // only honour the per-consumer prefetch (checked against RabbitMQ 4.3.5).
        $channel->basic_qos(0, 1, false);
        $topology->declare($channel);

        foreach ($subscriptions->consumerClasses() as $name => $class) {
            $channel->basic_consume($name, '', false, false, false, false,
                fn (AMQPMessage $message) => $this->deliver($message, $name, $class, $delivery));
        }

        while (true) {
            $idle = false;

            try {
                $channel->wait(null, false, self::WAIT_SECONDS);
            } catch (AMQPTimeoutException) {
                $idle = true;
            }

            if ($restartSignal->changed()) {
                return true;
            }

            if ($idle && $this->option('stop-when-empty') && $this->allEmpty($channel, $topology)) {
                return true;
            }
        }
    }

    /** @param  class-string  $consumer */
    private function deliver(AMQPMessage $message, string $consumerName, string $consumer, EventDelivery $delivery): void
    {
        $envelope = null;

        try {
            $envelope = RabbitMqEnvelope::fromMessage($message);
            $delivery->deliver($envelope, $consumer);
        } catch (Throwable $e) {
            Log::warning('Event delivery failed.', [
                'message_id' => $envelope->id ?? ($message->has('message_id') ? $message->get('message_id') : null),
                'name' => $envelope->name ?? ($message->has('type') ? $message->get('type') : null),
                'consumer' => $consumerName,
                'attempt' => $this->attemptOf($message),
                'error' => $e->getMessage(),
            ]);
            $message->reject(true);

            return;
        }

        $message->ack();
    }

    /** The broker counts the returns of a delivery in `x-delivery-count`: this is the attempt in progress. */
    private function attemptOf(AMQPMessage $message): int
    {
        $headers = $message->has('application_headers') ? $message->get('application_headers')->getNativeData() : [];

        return (int) ($headers['x-delivery-count'] ?? 0) + 1;
    }

    private function allEmpty(AMQPChannel $channel, RabbitMqTopology $topology): bool
    {
        foreach ($topology->consumerQueues() as $queue) {
            [, $ready] = $channel->queue_declare($queue, true);

            if ($ready > 0) {
                return false;
            }
        }

        return true;
    }

    private function close(AbstractConnection $connection): void
    {
        try {
            $connection->close();
        } catch (Throwable) {
            // The connection is already gone.
        }
    }
}
