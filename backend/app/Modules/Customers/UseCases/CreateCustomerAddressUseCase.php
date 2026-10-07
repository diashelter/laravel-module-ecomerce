<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerAddressDTO;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\Repositories\CustomerAddressRepository;
use App\Modules\Customers\Services\CustomerAddressService;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Shared\Exceptions\BusinessRuleException;

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
    public function execute(CustomerAccount $account, CustomerAddressDTO $data): CustomerAddress
    {
        $this->rules->assertHasRoomForAnother($this->addresses->countForCustomer($account->id));

        return $this->addresses->create(['customer_id' => $account->id, ...$data->toArray()]);
    }
}
