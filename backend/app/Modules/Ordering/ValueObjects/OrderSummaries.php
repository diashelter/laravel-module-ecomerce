<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A customer's orders as summaries, in the order they were read (newest first).
 *
 * @implements IteratorAggregate<int, OrderSummary>
 */
final readonly class OrderSummaries implements Countable, IteratorAggregate
{
    /** @var list<OrderSummary> */
    private array $summaries;

    public function __construct(OrderSummary ...$summaries)
    {
        $this->summaries = array_values($summaries);
    }

    public function first(): ?OrderSummary
    {
        return $this->summaries[0] ?? null;
    }

    public function count(): int
    {
        return count($this->summaries);
    }

    /** @return Traversable<int, OrderSummary> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->summaries);
    }
}
