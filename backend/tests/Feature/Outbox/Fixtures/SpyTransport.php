<?php

declare(strict_types=1);

namespace Tests\Feature\Outbox\Fixtures;

use App\Modules\Shared\Messaging\EventEnvelope;
use App\Modules\Shared\Messaging\EventTransport;
use Closure;
use RuntimeException;

/**
 * Keeps the envelopes it receives, in order. It throws RuntimeException('transport down') for the
 * message ids in $failing (every time), and calls $onPublish with each envelope it receives.
 */
final class SpyTransport implements EventTransport
{
    /** @var list<EventEnvelope> */
    public array $received = [];

    /** @var list<EventEnvelope> */
    public array $accepted = [];

    /** @param  list<string>  $failing */
    public function __construct(public array $failing = [], private readonly ?Closure $onPublish = null) {}

    public function publish(EventEnvelope $envelope): void
    {
        $this->received[] = $envelope;

        if ($this->onPublish !== null) {
            ($this->onPublish)($envelope);
        }

        if (in_array($envelope->id, $this->failing, true)) {
            throw new RuntimeException('transport down');
        }

        $this->accepted[] = $envelope;
    }
}
