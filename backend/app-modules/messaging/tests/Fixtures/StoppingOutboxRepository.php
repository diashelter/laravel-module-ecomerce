<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Fixtures;

use Modules\Messaging\Persistence\Repositories\OutboxMessageRepository;
use RuntimeException;

/**
 * Marks the messages as published and then throws RuntimeException('relay stopped before the
 * commit'), like a relay process that stops after the transport accepted and before its commit.
 */
final class StoppingOutboxRepository extends OutboxMessageRepository
{
    public function markPublished(array $ids): void
    {
        parent::markPublished($ids);

        throw new RuntimeException('relay stopped before the commit');
    }
}
