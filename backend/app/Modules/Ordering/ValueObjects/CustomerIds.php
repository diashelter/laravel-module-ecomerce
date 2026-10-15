<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

use App\Modules\Shared\Support\RecordId;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * A list of customer ids. Ids are canonical UUIDs and never repeated: a repetition is a bug upstream,
 * so it is refused instead of silently removed.
 *
 * @implements IteratorAggregate<int, string>
 */
final readonly class CustomerIds implements Countable, IteratorAggregate
{
    /** @var list<string> */
    private array $ids;

    public function __construct(string ...$ids)
    {
        $ids = array_values($ids);

        foreach ($ids as $id) {
            if (! RecordId::isCanonical($id)) {
                throw new InvalidArgumentException('Ids must be canonical UUIDs.');
            }
        }

        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Ids must not repeat.');
        }

        $this->ids = $ids;
    }

    /** @return list<string> for the query builder (`whereIn`, `sync`) */
    public function all(): array
    {
        return $this->ids;
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /** @return Traversable<int, string> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->ids);
    }
}
