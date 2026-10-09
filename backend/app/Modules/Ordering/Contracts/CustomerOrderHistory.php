<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Contracts;

use App\Modules\Ordering\ValueObjects\CustomerIds;
use App\Modules\Ordering\ValueObjects\OrderCountsByCustomer;
use App\Modules\Ordering\ValueObjects\OrderSummaries;

/**
 * Published by the ordering module for the customers module: a customer's purchase history,
 * as counts and order summaries, never as models.
 */
interface CustomerOrderHistory
{
    public function countForCustomer(int $customerId): int;

    /** One query for the whole list, whatever its size. */
    public function countPerCustomer(CustomerIds $customerIds): OrderCountsByCustomer;

    /** Newest first, with the id as tie breaker. */
    public function recentForCustomer(int $customerId, int $limit): OrderSummaries;
}
