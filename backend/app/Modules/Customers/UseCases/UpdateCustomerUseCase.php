<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Repositories\CustomerAccountRepository;
use App\Modules\Identity\Services\UserService;
use App\Modules\Ordering\Repositories\OrderRepository;

/**
 * Admin: updates a customer's data.
 */
final class UpdateCustomerUseCase
{
    public function __construct(
        private readonly CustomerAccountRepository $accounts,
        private readonly OrderRepository $orders,
        private readonly UserService $userService,
    ) {}

    public function execute(CustomerAccount $customer, UpdateUserProfileDTO $data): CustomerSummaryDTO
    {
        $account = $this->accounts->update($customer, $this->userService->profileChanges($data));

        return new CustomerSummaryDTO($account, $this->orders->countForCustomer($account->id));
    }
}
