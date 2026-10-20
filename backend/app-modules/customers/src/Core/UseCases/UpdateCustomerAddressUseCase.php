<?php

declare(strict_types=1);

namespace Modules\Customers\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Customers\Core\DTOs\CustomerAddressDTO;
use Modules\Customers\Persistence\Models\CustomerAddress;
use Modules\Customers\Persistence\Repositories\CustomerAddressRepository;

/**
 * Customer: replaces the fields of one of their own addresses. Past orders keep their copy.
 */
final class UpdateCustomerAddressUseCase
{
    public function __construct(private readonly CustomerAddressRepository $addresses) {}

    /**
     * @throws ModelNotFoundException when the address is not in the customer's own book
     */
    public function execute(string $customerId, string $addressId, CustomerAddressDTO $data): CustomerAddress
    {
        return $this->addresses->updateForCustomer($customerId, $addressId, $data->toArray());
    }
}
