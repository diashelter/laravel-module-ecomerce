<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Modules\Identity\Contracts\CustomerMetrics;
use App\Modules\Identity\Models\CustomerAccount;

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
