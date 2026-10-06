<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Ordering\Models\Order;

class OrderPolicy
{
    /** Only customers place orders (admins manage the store). */
    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
