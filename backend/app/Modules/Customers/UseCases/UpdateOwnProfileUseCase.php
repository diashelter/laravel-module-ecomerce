<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;
use App\Modules\Identity\Services\UserService;

/**
 * Customer: updates their own name, e-mail and, optionally, password.
 */
final class UpdateOwnProfileUseCase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserService $userService,
    ) {}

    public function execute(User $user, UpdateUserProfileDTO $data): User
    {
        return $this->users->update($user, $this->userService->profileChanges($data));
    }
}
