<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\Repositories\CustomerAddressRepository;

/**
 * Customer: removes one of their own addresses. Past orders keep their copy.
 */
final class DeleteCustomerAddressUseCase
{
    public function __construct(private readonly CustomerAddressRepository $addresses) {}

    public function execute(CustomerAddress $address): void
    {
        $this->addresses->delete($address);
    }
}
