<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Repositories\CustomerAccountRepository;
use App\Modules\Identity\Services\UserService;

/**
 * Customer: updates their own name, e-mail and, optionally, password.
 */
final class UpdateOwnProfileUseCase
{
    public function __construct(
        private readonly CustomerAccountRepository $accounts,
        private readonly UserService $userService,
    ) {}

    public function execute(CustomerAccount $account, UpdateUserProfileDTO $data): CustomerAccount
    {
        return $this->accounts->update($account, $this->userService->profileChanges($data));
    }
}
