<?php

declare(strict_types=1);

namespace Modules\Identity\Persistence\Queries;

use Modules\Identity\Contracts\CustomerMetrics;
use Modules\Identity\Persistence\Models\CustomerAccount;

/**
 * Counts the customer accounts for the admin dashboard.
 */
class CustomerMetricsQuery implements CustomerMetrics
{
    public function totalCustomers(): int
    {
        return CustomerAccount::query()->count();
    }
}
