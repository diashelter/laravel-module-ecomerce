<?php

declare(strict_types=1);

namespace Modules\Shared\Exceptions;

use RuntimeException;
use Throwable;

/**
 * A republication (the rebuild of a read model) stopped halfway. It says how many records it had recorded, and every
 * one of them is committed: running it again converges to the same read model, because the projector keeps the
 * greater version of each part.
 */
class RepublicationFailedException extends RuntimeException
{
    public function __construct(public readonly int $recorded, Throwable $previous)
    {
        parent::__construct("The republication failed after recording {$recorded} records: {$previous->getMessage()}", previous: $previous);
    }
}
