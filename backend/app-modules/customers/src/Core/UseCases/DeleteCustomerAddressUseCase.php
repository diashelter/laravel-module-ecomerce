<?php

declare(strict_types=1);

namespace Modules\Customers\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Customers\Persistence\Repositories\CustomerAddressRepository;

/**
 * Customer: removes one of their own addresses. Past orders keep their copy.
 */
final class DeleteCustomerAddressUseCase
{
    public function __construct(private readonly CustomerAddressRepository $addresses) {}

    /**
     * @throws ModelNotFoundException when the address is not in the customer's own book
     */
    public function execute(string $customerId, string $addressId): void
    {
        $this->addresses->deleteForCustomer($customerId, $addressId);
    }
}
