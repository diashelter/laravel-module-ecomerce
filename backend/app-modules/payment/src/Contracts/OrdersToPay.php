<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Modules\Payment\Core\ValueObjects\OrderToPay;

/**
 * Port of the payment module to whoever owns the orders (the ordering module, wired by the integration
 * package): what payment needs to know about the order it charges, in payment's own vocabulary. With no
 * module behind it, every call throws ServiceUnavailableException (503) before anything is written.
 */
interface OrdersToPay
{
    /**
     * Null when there is no such order among that customer's own: the order of another customer
     * answers the same as one that does not exist.
     */
    public function findForPayment(string $customerId, string $orderId): ?OrderToPay;
}
