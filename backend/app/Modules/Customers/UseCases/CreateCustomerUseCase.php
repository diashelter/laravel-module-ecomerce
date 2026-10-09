<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\DTOs\CreateUserDTO;

/**
 * Admin: creates a customer. Staff members are managed in Identity.
 */
final class CreateCustomerUseCase
{
    public function __construct(private readonly CustomerAccounts $accounts) {}

    public function execute(CreateUserDTO $data): CustomerSummaryDTO
    {
        $account = $this->accounts->register($data);

        // A brand new account has no orders yet: no need to ask the ordering side.
        return new CustomerSummaryDTO($account, ordersCount: 0);
    }
}
