<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\ValueObjects;

use ArrayIterator;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * Order count per period (`Y-m-d` for a day, `Y-m` for a month). A period without orders has no
 * entry in the query result, so this class answers zero for it instead of leaving every caller to
 * remember `?? 0`. Iterates only the periods that have orders: `period => total`.
 *
 * @implements IteratorAggregate<string, int>
 */
final readonly class OrderCountsByPeriod implements Countable, IteratorAggregate
{
    /** @var array<string, int> */
    private array $totals;

    /** @param  Collection<string, int>  $totalsByPeriod  `period => total`, as plucked from the query */
    public function __construct(Collection $totalsByPeriod)
    {
        $this->totals = $totalsByPeriod->map(fn ($total) => (int) $total)->all();
    }

    public function of(string $period): int
    {
        return $this->totals[$period] ?? 0;
    }

    public function count(): int
    {
        return count($this->totals);
    }

    /** @return Traversable<string, int> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->totals);
    }
}
