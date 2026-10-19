<?php

declare(strict_types=1);

namespace App\Modules\Shared\Console;

use App\Modules\Shared\Messaging\EventTransportFlag;
use App\Modules\Shared\Messaging\OutboxRelay;
use App\Modules\Shared\Messaging\RelayCycle;
use App\Modules\Shared\Messaging\RestartSignal;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Sleep;

/**
 * The outbox relay process (the outbox-relay container): publishes the pending messages within
 * about a second, prunes the old published ones at start and then once an hour, and stops after the
 * cycle in progress when `php artisan queue:restart` runs, like the queue workers.
 */
final class RelayOutboxCommand extends Command
{
    private const IDLE_WAIT_MILLISECONDS = 500;

    /** Longer than the idle wait: with Redis down, the relay would log two warnings a second. */
    private const FAILURE_WAIT_SECONDS = 5;

    protected $signature = 'outbox:relay {--once : Publish the pending messages once and exit}';

    protected $description = 'Publish the pending outbox messages to the event transport';

    public function handle(OutboxRelay $relay, Cache $cache, EventTransportFlag $flag): int
    {
        $relay->prune();
        $nextPruneAt = now()->addHour();

        if ($this->option('once')) {
            return $this->publish($relay, $flag) === RelayCycle::Failed ? self::FAILURE : self::SUCCESS;
        }

        $restartSignal = new RestartSignal($cache);

        while (true) {
            if (now()->greaterThanOrEqualTo($nextPruneAt)) {
                $relay->prune();
                $nextPruneAt = now()->addHour();
            }

            $cycle = $this->publish($relay, $flag);

            if ($restartSignal->changed()) {
                return self::SUCCESS;
            }

            // A cycle that published goes straight to the next one: the batch may have been full.
            if ($cycle === RelayCycle::Idle) {
                Sleep::for(self::IDLE_WAIT_MILLISECONDS)->milliseconds();
            } elseif ($cycle === RelayCycle::Failed) {
                Sleep::for(self::FAILURE_WAIT_SECONDS)->seconds();
            }
        }
    }

    private function publish(OutboxRelay $relay, EventTransportFlag $flag): RelayCycle
    {
        // Pennant keeps the flag in memory: a value changed by `events:transport` is read again each cycle.
        $flag->refresh();

        $cycle = $relay->publishPending();

        // One line on the process output (make logs-relay); the message id and the error are in the log.
        if ($cycle === RelayCycle::Failed) {
            $this->components->warn('The transport refused an outbox message: it and the later ones stay pending.');
        }

        return $cycle;
    }
}
