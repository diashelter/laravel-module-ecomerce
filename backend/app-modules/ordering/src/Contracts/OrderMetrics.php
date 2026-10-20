<?php

declare(strict_types=1);

namespace Modules\Ordering\Contracts;

use Carbon\CarbonInterface;
use Modules\Ordering\Core\ValueObjects\OrderCountsByPeriod;

/**
 * Published by the ordering module for the admin dashboard: how many orders exist, in total and per
 * day or month, as data. Implemented by a query of the ordering module, since it only reads.
 */
interface OrderMetrics
{
    public function totalOrders(): int;

    /** The orders created on or after the date, per day (`Y-m-d`). */
    public function countPerDaySince(CarbonInterface $start): OrderCountsByPeriod;

    /** The orders created on or after the date, per month (`Y-m`). */
    public function countPerMonthSince(CarbonInterface $start): OrderCountsByPeriod;
}
