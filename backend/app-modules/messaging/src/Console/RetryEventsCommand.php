<?php

declare(strict_types=1);

namespace Modules\Messaging\Console;

use Illuminate\Console\Command;
use Modules\Messaging\Core\RabbitMq\DeadDeliveries;
use RuntimeException;
use Throwable;

/**
 * Returns the dead deliveries of the RabbitMQ consumers to the queue of the consumer each one
 * failed in (make events-retry), all of them or the ones of one message. The counterpart of
 * `queue:retry` for the Laravel queue.
 */
final class RetryEventsCommand extends Command
{
    protected $signature = 'events:retry {id? : The message id; without it, every dead delivery returns}';

    protected $description = 'Return the dead event deliveries in RabbitMQ to their consumers';

    public function handle(DeadDeliveries $deliveries): int
    {
        try {
            $returned = $deliveries->retry($this->argument('id'));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error("A devolução parou: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($returned === []) {
            $this->line('Nenhuma entrega morta.');

            return self::SUCCESS;
        }

        foreach ($returned as $consumer => $count) {
            $this->line("{$consumer}: {$count}");
        }

        return self::SUCCESS;
    }
}
