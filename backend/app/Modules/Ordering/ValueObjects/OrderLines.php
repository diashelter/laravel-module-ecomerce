<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * The lines of a new order. The total is derived from the lines, so it cannot diverge from them.
 *
 * @implements IteratorAggregate<int, OrderLine>
 */
final readonly class OrderLines implements Countable, IteratorAggregate
{
    /** @var list<OrderLine> */
    private array $lines;

    public function __construct(OrderLine ...$lines)
    {
        $this->lines = array_values($lines);
    }

    public function totalCents(): int
    {
        return array_sum(array_map(fn (OrderLine $line) => $line->subtotalCents(), $this->lines));
    }

    public function count(): int
    {
        return count($this->lines);
    }

    /** @return Traversable<int, OrderLine> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->lines);
    }
}
