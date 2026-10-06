<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Repositories\UserRepository;

/**
 * Admin: creates a customer. Administrators only come from the seeder.
 */
final class CreateCustomerUseCase
{
    public function __construct(private readonly UserRepository $users) {}

    public function execute(CreateUserDTO $data): CustomerSummaryDTO
    {
        $account = $this->users->createWithRole($data->toArray(), UserRole::Customer);

        // A brand new account has no orders yet: no need to ask the ordering side.
        return new CustomerSummaryDTO($account, ordersCount: 0);
    }
}
