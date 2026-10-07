<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\Repositories\CustomerAccountRepository;

/**
 * Admin: creates a customer. Staff members are managed in Identity.
 */
final class CreateCustomerUseCase
{
    public function __construct(private readonly CustomerAccountRepository $accounts) {}

    public function execute(CreateUserDTO $data): CustomerSummaryDTO
    {
        $account = $this->accounts->create($data->toArray());

        // A brand new account has no orders yet: no need to ask the ordering side.
        return new CustomerSummaryDTO($account, ordersCount: 0);
    }
}
