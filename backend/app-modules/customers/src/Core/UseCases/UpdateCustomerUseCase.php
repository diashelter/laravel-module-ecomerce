<?php

declare(strict_types=1);

namespace Modules\Customers\Core\UseCases;

use Modules\Customers\Core\DTOs\CustomerSummaryDTO;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Ordering\Contracts\CustomerOrderHistory;

/**
 * Admin: updates a customer's data.
 */
final class UpdateCustomerUseCase
{
    public function __construct(
        private readonly CustomerAccounts $accounts,
        private readonly CustomerOrderHistory $orders,
    ) {}

    public function execute(string $customerId, UpdateUserProfileDTO $data): CustomerSummaryDTO
    {
        $account = $this->accounts->updateProfile($customerId, $data);

        return new CustomerSummaryDTO($account, $this->orders->countForCustomer($account->id));
    }
}
