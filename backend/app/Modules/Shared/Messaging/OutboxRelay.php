<?php

declare(strict_types=1);

namespace App\Modules\Shared\Messaging;

use App\Modules\Shared\Repositories\OutboxMessageRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands the pending outbox messages to the event transport and marks the ones it accepted. Run by
 * the `outbox:relay` command in the outbox-relay container.
 *
 * Delivery is at least once: if the process dies after the transport accepted a message and before
 * the commit, the message is published again, and the consumers are idempotent.
 */
final class OutboxRelay
{
    public const BATCH_SIZE = 100;

    public const RETENTION_DAYS = 7;

    public function __construct(
        private readonly OutboxMessageRepository $messages,
        private readonly EventTransport $transport,
    ) {}

    /**
     * One cycle, in one transaction: claims up to a batch of pending messages, oldest first, and
     * publishes them in order. The first failure stops the cycle and leaves that message and the
     * later ones pending; the ones already accepted are still marked.
     */
    public function publishPending(): RelayCycle
    {
        return DB::transaction(function (): RelayCycle {
            $envelopes = $this->messages->claimPending(self::BATCH_SIZE);
            $published = [];
            $cycle = $envelopes === [] ? RelayCycle::Idle : RelayCycle::Published;

            foreach ($envelopes as $envelope) {
                try {
                    $this->transport->publish($envelope);
                } catch (Throwable $e) {
                    Log::warning('Outbox message could not be published.', [
                        'message_id' => $envelope->id,
                        'name' => $envelope->name,
                        'error' => $e->getMessage(),
                    ]);
                    $cycle = RelayCycle::Failed;

                    break;
                }

                $published[] = $envelope->id;
            }

            if ($published !== []) {
                $this->messages->markPublished($published);
            }

            return $cycle;
        });
    }

    /** Deletes the messages published more than 7 days ago. The pending ones are kept, however old. */
    public function prune(): void
    {
        $this->messages->prunePublishedBefore(now()->subDays(self::RETENTION_DAYS));
    }
}
