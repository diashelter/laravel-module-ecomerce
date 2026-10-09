<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\ValueObjects\CustomerProfile;

/**
 * Customer: updates their own name, e-mail and, optionally, password.
 */
final class UpdateOwnProfileUseCase
{
    public function __construct(private readonly CustomerAccounts $accounts) {}

    public function execute(int $customerId, UpdateUserProfileDTO $data): CustomerProfile
    {
        return $this->accounts->updateProfile($customerId, $data);
    }
}
