<?php

declare(strict_types=1);

namespace Modules\Messaging\Core\RabbitMq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use RuntimeException;
use Throwable;

/**
 * Opens the connection to the broker from config/rabbitmq.php. The only place that does, so every
 * failure to reach the broker reads the same ("RabbitMQ ...") and a test replaces it in one binding.
 */
class RabbitMqConnector
{
    /**
     * The read/write timeout is longer than the consumer's wait for a delivery, which is shorter
     * than the publisher confirmation wait.
     *
     * @throws RuntimeException when the broker cannot be reached or refuses the credentials or the vhost
     */
    public function connect(): AMQPStreamConnection
    {
        $host = (string) config('rabbitmq.host');
        $port = (int) config('rabbitmq.port');

        try {
            return new AMQPStreamConnection(
                $host,
                $port,
                (string) config('rabbitmq.user'),
                (string) config('rabbitmq.password'),
                (string) config('rabbitmq.vhost'),
                connection_timeout: 3.0,
                read_write_timeout: 10.0,
            );
        } catch (Throwable $e) {
            throw new RuntimeException("RabbitMQ is unreachable at {$host}:{$port}: {$e->getMessage()}", previous: $e);
        }
    }
}
