<?php

declare(strict_types=1);

namespace App\Modules\Customers\Policies;

use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Contracts\Auth\Authenticatable;

class CustomerAddressPolicy
{
    public function update(Authenticatable $account, CustomerAddress $address): bool
    {
        return $address->customer_id === $account->getAuthIdentifier();
    }

    public function delete(Authenticatable $account, CustomerAddress $address): bool
    {
        return $address->customer_id === $account->getAuthIdentifier();
    }
}
