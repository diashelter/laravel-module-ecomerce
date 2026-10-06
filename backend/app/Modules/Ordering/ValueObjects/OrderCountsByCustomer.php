<?php

declare(strict_types=1);

namespace App\Modules\Ordering\ValueObjects;

use ArrayIterator;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * Order count per customer. A customer without orders has no entry in the query result,
 * so this class answers zero for them instead of leaving every caller to remember `?? 0`.
 * Iterates only the customers that have orders: `customer_id => total`.
 *
 * @implements IteratorAggregate<int, int>
 */
final readonly class OrderCountsByCustomer implements Countable, IteratorAggregate
{
    /** @var array<int, int> */
    private array $totals;

    /** @param  Collection<int, int>  $totalsByCustomerId  `customer_id => total`, as plucked from the query */
    public function __construct(Collection $totalsByCustomerId)
    {
        $this->totals = $totalsByCustomerId->map(fn ($total) => (int) $total)->all();
    }

    public function countFor(int $customerId): int
    {
        return $this->totals[$customerId] ?? 0;
    }

    public function count(): int
    {
        return count($this->totals);
    }

    /** @return Traversable<int, int> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->totals);
    }
}
