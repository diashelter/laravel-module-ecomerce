<?php

declare(strict_types=1);

namespace Modules\Identity\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Identity\Core\DTOs\UpdateStaffMemberDTO;
use Modules\Identity\Persistence\Models\User;
use Modules\Identity\Persistence\Repositories\UserRepository;
use Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: updates a staff member. An admin cannot change their own role, so the last admin
 * does not demote themselves by accident.
 */
final class UpdateStaffMemberUseCase
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @throws BusinessRuleException
     * @throws ModelNotFoundException
     */
    public function execute(User $actor, string $targetId, UpdateStaffMemberDTO $data): User
    {
        $target = $this->users->findOrFail($targetId);

        if ($actor->is($target) && $data->role !== $target->role) {
            throw new BusinessRuleException('Você não pode alterar o próprio papel.');
        }

        return $this->users->updateWithRole($target, $data->profile->toArray(), $data->role);
    }
}
