<?php

declare(strict_types=1);

namespace Modules\Customers\Core\UseCases;

use Modules\Customers\Core\DTOs\CustomerAddressDTO;
use Modules\Customers\Core\Services\CustomerAddressService;
use Modules\Customers\Persistence\Models\CustomerAddress;
use Modules\Customers\Persistence\Repositories\CustomerAddressRepository;
use Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Customer: adds an address to their own address book, up to the limit.
 */
final class CreateCustomerAddressUseCase
{
    public function __construct(
        private readonly CustomerAddressRepository $addresses,
        private readonly CustomerAddressService $rules,
    ) {}

    /**
     * @throws BusinessRuleException
     */
    public function execute(string $customerId, CustomerAddressDTO $data): CustomerAddress
    {
        $this->rules->assertHasRoomForAnother($this->addresses->countForCustomer($customerId));

        return $this->addresses->create(['customer_id' => $customerId, ...$data->toArray()]);
    }
}
