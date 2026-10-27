<?php

declare(strict_types=1);

namespace Modules\Messaging\Contracts;

use InvalidArgumentException;
use LogicException;

/**
 * Where a use case records the events it announces. The message is written in the transaction of
 * the change, so the commit keeps both and a rollback drops both; the outbox relay publishes it
 * afterwards. Nothing dispatches an event directly.
 */
interface EventOutbox
{
    /**
     * @throws LogicException when no transaction is open: an event recorded outside the change it
     *                        announces is a programming error
     * @throws InvalidArgumentException when a payload value is not null, a scalar or a flat list of scalars
     */
    public function record(DomainEvent $event): void;
}
