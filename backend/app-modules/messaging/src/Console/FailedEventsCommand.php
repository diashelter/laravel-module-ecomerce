<?php

declare(strict_types=1);

namespace Modules\Messaging\Console;

use Illuminate\Console\Command;
use Modules\Messaging\Core\RabbitMq\DeadDeliveries;
use RuntimeException;

/**
 * Lists the deliveries in the dead queues of the RabbitMQ consumers (make events-failed), without
 * taking any of them from the queue. The counterpart of `queue:failed` for the Laravel queue.
 */
final class FailedEventsCommand extends Command
{
    protected $signature = 'events:failed';

    protected $description = 'List the event deliveries that failed three times in RabbitMQ';

    public function handle(DeadDeliveries $deliveries): int
    {
        try {
            $rows = $deliveries->all();
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->line('Nenhuma entrega morta.');

            return self::SUCCESS;
        }

        $this->table(
            ['Consumidor', 'Evento', 'Mensagem', 'Ocorreu em', 'Requisição', 'Entregas'],
            array_map(fn (array $row) => array_values($row), $rows),
        );

        return self::SUCCESS;
    }
}
