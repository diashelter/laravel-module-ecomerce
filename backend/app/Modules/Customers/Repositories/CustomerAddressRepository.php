<?php

declare(strict_types=1);

namespace App\Modules\Customers\Repositories;

use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Ordering\Contracts\DeliveryAddressBook;
use App\Modules\Ordering\ValueObjects\DeliveryAddress;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Also answers the ordering side's DeliveryAddressBook contract (bound in CustomersServiceProvider).
 *
 * @extends BaseRepository<CustomerAddress>
 */
class CustomerAddressRepository extends BaseRepository implements DeliveryAddressBook
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

    /**
     * The copy of one address, only when it is in that customer's own book: the id of another
     * customer's address answers the same as one that does not exist.
     */
    public function find(int $customerId, int $addressId): ?DeliveryAddress
    {
        $address = $this->query()->where('customer_id', $customerId)->find($addressId);

        if ($address === null) {
            return null;
        }

        return new DeliveryAddress(
            $address->recipient_name,
            $address->postal_code,
            $address->street,
            $address->number,
            $address->complement,
            $address->district,
            $address->city,
            $address->state,
        );
    }
}
