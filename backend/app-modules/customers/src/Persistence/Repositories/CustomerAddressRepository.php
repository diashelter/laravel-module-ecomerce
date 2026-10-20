<?php

declare(strict_types=1);

namespace Modules\Customers\Persistence\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Customers\Persistence\Models\CustomerAddress;
use Modules\Ordering\Contracts\DeliveryAddressBook;
use Modules\Ordering\Core\ValueObjects\DeliveryAddress;
use Modules\Shared\Repositories\BaseRepository;

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
    public function newestFirstForCustomer(string $customerId): Collection
    {
        return $this->query()
            ->where('customer_id', $customerId)
            ->latest()
            ->latest('id')
            ->get();
    }

    public function countForCustomer(string $customerId): int
    {
        return $this->query()->where('customer_id', $customerId)->count();
    }

    /**
     * Replaces the fields of an address of that customer's own book.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ModelNotFoundException when the address is another customer's or does not exist
     */
    public function updateForCustomer(string $customerId, string $addressId, array $attributes): CustomerAddress
    {
        return $this->update($this->query()->where('customer_id', $customerId)->findOrFail($addressId), $attributes);
    }

    /**
     * Deletes an address of that customer's own book with a single DELETE, without reading it.
     *
     * @throws ModelNotFoundException when the address is another customer's or does not exist
     */
    public function deleteForCustomer(string $customerId, string $addressId): void
    {
        if ($this->query()->where('customer_id', $customerId)->whereKey($addressId)->delete() === 0) {
            throw (new ModelNotFoundException)->setModel($this->model(), [$addressId]);
        }
    }

    /**
     * The copy of one address, only when it is in that customer's own book: the id of another
     * customer's address answers the same as one that does not exist.
     */
    public function find(string $customerId, string $addressId): ?DeliveryAddress
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
