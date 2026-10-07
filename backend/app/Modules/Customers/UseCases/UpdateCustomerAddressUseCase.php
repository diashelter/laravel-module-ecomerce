<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerAddressDTO;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\Repositories\CustomerAddressRepository;

/**
 * Customer: replaces the fields of one of their own addresses. Past orders keep their copy.
 */
final class UpdateCustomerAddressUseCase
{
    public function __construct(private readonly CustomerAddressRepository $addresses) {}

    public function execute(CustomerAddress $address, CustomerAddressDTO $data): CustomerAddress
    {
        return $this->addresses->update($address, $data->toArray());
    }
}
