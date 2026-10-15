<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerAddressDTO;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\Repositories\CustomerAddressRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
