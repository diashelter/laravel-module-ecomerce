<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Contracts;

use App\Modules\Ordering\ValueObjects\OrderForPayment;

/**
 * Published by the ordering module for the payment module: what payment needs to know about
 * an order, as data. Payment decides whether it can be paid; only ordering changes its status.
 */
interface PayableOrders
{
    /**
     * Null when there is no such order.
     */
    public function findForPayment(int $orderId): ?OrderForPayment;
}
