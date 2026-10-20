<?php

declare(strict_types=1);

namespace Modules\Ordering\Contracts;

use Modules\Ordering\Core\ValueObjects\OrderForPayment;

/**
 * Published by the ordering module for the payment module: what payment needs to know about
 * an order, as data. Payment decides whether it can be paid; only ordering changes its status.
 */
interface PayableOrders
{
    /**
     * Null when there is no such order among that customer's own: the order of another customer
     * answers the same as one that does not exist.
     */
    public function findForPayment(string $customerId, string $orderId): ?OrderForPayment;
}
