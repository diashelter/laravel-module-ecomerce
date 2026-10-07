<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Policies;

use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Ordering\Models\Order;

class OrderPolicy
{
    /** Every shopper account may place orders (staff have no store account). */
    public function create(CustomerAccount $account): bool
    {
        return true;
    }

    public function view(CustomerAccount $account, Order $order): bool
    {
        return $order->customer_id === $account->id;
    }

    public function pay(CustomerAccount $account, Order $order): bool
    {
        return $order->customer_id === $account->id;
    }
}
