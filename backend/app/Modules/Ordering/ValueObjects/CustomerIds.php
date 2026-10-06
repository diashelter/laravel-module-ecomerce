<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * A list of customer ids. Ids are positive and never repeated: a repetition is a bug upstream,
 * so it is refused instead of silently removed.
 *
 * @implements IteratorAggregate<int, int>
 */
final readonly class CustomerIds implements Countable, IteratorAggregate
{
    /** @var list<int> */
    private array $ids;

    public function __construct(int ...$ids)
    {
        $ids = array_values($ids);

        foreach ($ids as $id) {
            if ($id < 1) {
                throw new InvalidArgumentException('Ids must be positive integers.');
            }
        }

        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('Ids must not repeat.');
        }

        $this->ids = $ids;
    }

    /** @return list<int> for the query builder (`whereIn`, `sync`) */
    public function all(): array
    {
        return $this->ids;
    }

    public function count(): int
    {
        return count($this->ids);
    }

    /** @return Traversable<int, int> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->ids);
    }
}
