<?php

declare(strict_types=1);

namespace Modules\Identity\Contracts;

/**
 * Published by identity for the admin dashboard: how many customer accounts exist, as data. Staff
 * users are never counted. Implemented by a query of identity, since it only reads.
 */
interface CustomerMetrics
{
    public function totalCustomers(): int;
}
