<?php

declare(strict_types=1);

namespace App\Modules\Customers\Policies;

use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Identity\Models\CustomerAccount;

class CustomerAddressPolicy
{
    public function update(CustomerAccount $account, CustomerAddress $address): bool
    {
        return $address->customer_id === $account->id;
    }

    public function delete(CustomerAccount $account, CustomerAddress $address): bool
    {
        return $address->customer_id === $account->id;
    }
}
