<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;
use App\Modules\Identity\Services\UserService;
use App\Modules\Ordering\Repositories\OrderRepository;

/**
 * Admin: updates a customer's data (authorized by UserPolicy in the controller).
 */
final class UpdateCustomerUseCase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly OrderRepository $orders,
        private readonly UserService $userService,
    ) {}

    public function execute(User $user, UpdateUserProfileDTO $data): CustomerSummaryDTO
    {
        $account = $this->users->update($user, $this->userService->profileChanges($data));

        return new CustomerSummaryDTO($account, $this->orders->countForCustomer($account->id));
    }
}
