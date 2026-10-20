<?php

declare(strict_types=1);

namespace Modules\Integration\Payment;

use Modules\Payment\Contracts\OrdersToPay;
use Modules\Payment\Core\ValueObjects\OrderToPay;
use Modules\Shared\Exceptions\ServiceUnavailableException;

/**
 * The order port of the payment module when the ordering module is not installed: every call answers 503,
 * before the payment module charges or writes anything.
 */
final class UnavailableOrdersToPay implements OrdersToPay
{
    public function findForPayment(string $customerId, string $orderId): ?OrderToPay
    {
        throw new ServiceUnavailableException;
    }
}
