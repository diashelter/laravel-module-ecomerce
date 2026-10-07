<?php

declare(strict_types=1);

namespace App\Modules\Customers\Repositories;

use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<CustomerAddress>
 */
class CustomerAddressRepository extends BaseRepository
{
    protected function model(): string
    {
        return CustomerAddress::class;
    }

    /** @return Collection<int, CustomerAddress> */
    public function newestFirstForCustomer(int $customerId): Collection
    {
        return $this->query()
            ->where('customer_id', $customerId)
            ->latest()
            ->latest('id')
            ->get();
    }

    public function countForCustomer(int $customerId): int
    {
        return $this->query()->where('customer_id', $customerId)->count();
    }
}
