<?php

declare(strict_types=1);

namespace App\Modules\Shared\Console;

use App\Modules\Shared\Messaging\OutboxRelay;
use App\Modules\Shared\Messaging\RelayCycle;
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
    /** The cache key `queue:restart` writes, the same signal the queue workers watch. */
    public const RESTART_SIGNAL = 'illuminate:queue:restart';

    private const IDLE_WAIT_MILLISECONDS = 500;

    /** Longer than the idle wait: with Redis down, the relay would log two warnings a second. */
    private const FAILURE_WAIT_SECONDS = 5;

    protected $signature = 'outbox:relay {--once : Publish the pending messages once and exit}';

    protected $description = 'Publish the pending outbox messages to the event transport';

    public function handle(OutboxRelay $relay, Cache $cache): int
    {
        $relay->prune();
        $nextPruneAt = now()->addHour();

        if ($this->option('once')) {
            return $relay->publishPending() === RelayCycle::Failed ? self::FAILURE : self::SUCCESS;
        }

        $restartSignal = $cache->get(self::RESTART_SIGNAL);

        while (true) {
            if (now()->greaterThanOrEqualTo($nextPruneAt)) {
                $relay->prune();
                $nextPruneAt = now()->addHour();
            }

            $cycle = $relay->publishPending();

            if ($cache->get(self::RESTART_SIGNAL) !== $restartSignal) {
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
}
