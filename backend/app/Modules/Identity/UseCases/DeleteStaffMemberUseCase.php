<?php

declare(strict_types=1);

namespace App\Modules\Identity\UseCases;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;
use App\Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Admin: removes a staff member. Their session ends on the next request, because the guard
 * no longer finds the account.
 */
final class DeleteStaffMemberUseCase
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @throws BusinessRuleException
     */
    public function execute(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new BusinessRuleException('Você não pode remover a própria conta.');
        }

        $this->users->delete($target);
    }
}
