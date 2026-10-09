<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Ordering\Contracts\CustomerOrderHistory;

/**
 * Admin: updates a customer's data.
 */
final class UpdateCustomerUseCase
{
    public function __construct(
        private readonly CustomerAccounts $accounts,
        private readonly CustomerOrderHistory $orders,
    ) {}

    public function execute(int $customerId, UpdateUserProfileDTO $data): CustomerSummaryDTO
    {
        $account = $this->accounts->updateProfile($customerId, $data);

        return new CustomerSummaryDTO($account, $this->orders->countForCustomer($account->id));
    }
}
