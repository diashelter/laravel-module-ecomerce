<?php

declare(strict_types=1);

namespace App\Modules\Identity\UseCases;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Admin: removes a staff member. Their session ends on the next request, because the guard
 * no longer finds the account.
 */
final class DeleteStaffMemberUseCase
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @throws BusinessRuleException
     * @throws ModelNotFoundException
     */
    public function execute(User $actor, string $targetId): void
    {
        if ($actor->getKey() === $targetId) {
            throw new BusinessRuleException('Você não pode remover a própria conta.');
        }

        $this->users->deleteById($targetId);
    }
}
