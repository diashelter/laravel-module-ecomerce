<?php

declare(strict_types=1);

namespace Modules\Messaging\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Messaging\Core\EventTransportFlag;

/**
 * Shows or changes the `event-transport` feature flag (make event-transport). Without a value it
 * prints the current one; with one it stores it as the global value, and the outbox relay publishes
 * through the new transport from its next cycle, without a restart. A change is logged with who made it.
 */
final class EventTransportCommand extends Command
{
    protected $signature = 'events:transport {value? : redis or rabbitmq} {--by= : Who is changing it, for the log}';

    protected $description = 'Show or change the transport the outbox relay publishes events through';

    public function handle(EventTransportFlag $flag): int
    {
        $value = $this->argument('value');
        $current = $flag->current();

        if ($value === null) {
            $this->line("Transporte atual: {$current}");

            return self::SUCCESS;
        }

        if (! $flag->accepts($value)) {
            $this->components->error("Transporte inválido: {$value}. Valores aceitos: ".implode(', ', EventTransportFlag::VALUES).'.');

            return self::FAILURE;
        }

        if ($value === $current) {
            $this->line("O transporte já é {$value}.");

            return self::SUCCESS;
        }

        $flag->change($value);

        Log::info('The event transport was changed.', [
            'previous' => $current,
            'current' => $value,
            'changed_by' => $this->option('by') ?: 'unknown',
        ]);

        $this->line("Transporte: {$current} -> {$value}");

        return self::SUCCESS;
    }
}
