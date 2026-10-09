<?php

declare(strict_types=1);

namespace App\Modules\Payment\Policies;

use App\Modules\Ordering\ValueObjects\OrderForPayment;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Only the customer who placed an order may pay it.
 */
class OrderPaymentPolicy
{
    public function pay(Authenticatable $account, OrderForPayment $order): bool
    {
        return $order->customerId === $account->getAuthIdentifier();
    }
}
