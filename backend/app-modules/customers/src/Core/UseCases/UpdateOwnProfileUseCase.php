<?php

declare(strict_types=1);

namespace Modules\Customers\Core\UseCases;

use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Identity\Core\ValueObjects\CustomerProfile;

/**
 * Customer: updates their own name, e-mail and, optionally, password.
 */
final class UpdateOwnProfileUseCase
{
    public function __construct(private readonly CustomerAccounts $accounts) {}

    public function execute(string $customerId, UpdateUserProfileDTO $data): CustomerProfile
    {
        return $this->accounts->updateProfile($customerId, $data);
    }
}
