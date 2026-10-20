<?php

declare(strict_types=1);

namespace Modules\Messaging\Persistence\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use LogicException;
use Modules\Messaging\Contracts\DomainEvent;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Messaging\Core\EventEnvelope;
use Modules\Messaging\Persistence\Models\OutboxMessage;
use Modules\Shared\Http\Middleware\AssignRequestId;
use Modules\Shared\Repositories\BaseRepository;

/**
 * The outbox table: the use cases record into it (EventOutbox, bound in AppServiceProvider) and the
 * outbox relay claims, marks and prunes its rows.
 *
 * @extends BaseRepository<OutboxMessage>
 */
class OutboxMessageRepository extends BaseRepository implements EventOutbox
{
    protected function model(): string
    {
        return OutboxMessage::class;
    }

    /**
     * The transactions that count are the ones after-commit callbacks would wait for: in the tests,
     * the RefreshDatabase wrapper is left out, so a missing transaction is still refused there.
     */
    public function record(DomainEvent $event): void
    {
        if (app('db.transactions')->callbackApplicableTransactions()->isEmpty()) {
            throw new LogicException('The event '.$event::eventName().' must be recorded inside the transaction of the change it announces.');
        }

        $payload = $event->toPayload();

        foreach ($payload as $key => $value) {
            if ($value !== null && ! is_scalar($value)) {
                throw new InvalidArgumentException("The payload value [{$key}] of the event ".$event::eventName().' is not a scalar or null.');
            }
        }

        $this->create([
            'name' => $event::eventName(),
            'version' => $event::eventVersion(),
            'payload' => $payload,
            'request_id' => Context::get(AssignRequestId::CONTEXT_KEY),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Locks up to $limit pending messages, oldest first. A message another relay holds is skipped,
     * so two relays never publish it at the same time. Call it inside a transaction.
     *
     * @return list<EventEnvelope>
     */
    public function claimPending(int $limit): array
    {
        return $this->query()
            ->whereNull('published_at')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit($limit)
            ->lock('for update skip locked')
            ->get()
            ->map(fn (OutboxMessage $message) => new EventEnvelope(
                $message->id,
                $message->name,
                $message->version,
                $message->payload,
                $message->occurred_at,
                $message->request_id,
            ))
            ->all();
    }

    /** @param  list<string>  $ids */
    public function markPublished(array $ids): void
    {
        $this->query()->whereKey($ids)->update(['published_at' => now()]);
    }

    /** Deletes the messages published before the cutoff. A pending message is never deleted. */
    public function prunePublishedBefore(CarbonInterface $cutoff): void
    {
        $this->query()->where('published_at', '<', $cutoff)->delete();
    }
}
