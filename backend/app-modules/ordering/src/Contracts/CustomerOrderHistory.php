<?php

declare(strict_types=1);

namespace Modules\Ordering\Contracts;

use Modules\Ordering\Core\ValueObjects\CustomerIds;
use Modules\Ordering\Core\ValueObjects\OrderCountsByCustomer;
use Modules\Ordering\Core\ValueObjects\OrderSummaries;

/**
 * Published by the ordering module for the customers module: a customer's purchase history,
 * as counts and order summaries, never as models.
 */
interface CustomerOrderHistory
{
    public function countForCustomer(string $customerId): int;

    /** One query for the whole list, whatever its size. */
    public function countPerCustomer(CustomerIds $customerIds): OrderCountsByCustomer;

    /** Newest first, with the id as tie breaker. */
    public function recentForCustomer(string $customerId, int $limit): OrderSummaries;
}
