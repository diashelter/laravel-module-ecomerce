<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Contracts;

use App\Modules\Ordering\ValueObjects\OrderCountsByPeriod;
use Carbon\CarbonInterface;

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
