<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Policies;

use App\Modules\Ordering\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;

class OrderPolicy
{
    public function view(Authenticatable $account, Order $order): bool
    {
        return $order->customer_id === $account->getAuthIdentifier();
    }
}
