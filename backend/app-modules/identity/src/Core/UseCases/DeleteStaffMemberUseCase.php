<?php

declare(strict_types=1);

namespace Modules\Identity\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Identity\Persistence\Models\User;
use Modules\Identity\Persistence\Repositories\UserRepository;
use Modules\Shared\Exceptions\BusinessRuleException;

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
