<?php

declare(strict_types=1);

namespace App\Modules\Catalog\ValueObjects;

use App\Modules\Catalog\Enums\ProductStatus;
use Countable;
use Illuminate\Support\Collection;

/**
 * Product count per status. A status without products has no entry in the query result, so this
 * class answers zero for it instead of leaving every caller to remember `?? 0`.
 */
final readonly class ProductCountsByStatus implements Countable
{
    /** @var array<string, int> */
    private array $totals;

    /** @param  Collection<string, int>  $totalsByStatus  `status => total`, as plucked from the query */
    public function __construct(Collection $totalsByStatus)
    {
        $this->totals = $totalsByStatus->map(fn ($total) => (int) $total)->all();
    }

    public function of(ProductStatus $status): int
    {
        return $this->totals[$status->value] ?? 0;
    }

    /** All the products, whatever their status. */
    public function total(): int
    {
        return array_sum($this->totals);
    }

    /** The number of statuses that have products. */
    public function count(): int
    {
        return count($this->totals);
    }
}
