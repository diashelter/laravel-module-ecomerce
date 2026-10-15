<?php

declare(strict_types=1);

namespace App\Modules\Identity\UseCases;

use App\Modules\Identity\DTOs\UpdateStaffMemberDTO;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
