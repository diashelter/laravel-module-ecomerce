<?php

declare(strict_types=1);

namespace App\Modules\Identity\UseCases;

use App\Modules\Identity\DTOs\UpdateStaffMemberDTO;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;
use App\Modules\Identity\Services\UserService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: updates a staff member. An admin cannot change their own role, so the last admin
 * does not demote themselves by accident.
 */
final class UpdateStaffMemberUseCase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserService $userService,
    ) {}

    /**
     * @throws BusinessRuleException
     */
    public function execute(User $actor, User $target, UpdateStaffMemberDTO $data): User
    {
        if ($actor->is($target) && $data->role !== $target->role) {
            throw new BusinessRuleException('Você não pode alterar o próprio papel.');
        }

        return $this->users->updateWithRole($target, $this->userService->profileChanges($data->profile), $data->role);
    }
}
