<?php

declare(strict_types=1);

namespace Modules\Identity\Core\UseCases;

use Modules\Identity\Core\DTOs\CreateStaffMemberDTO;
use Modules\Identity\Persistence\Models\User;
use Modules\Identity\Persistence\Repositories\UserRepository;

/**
 * Admin: creates a staff member with the chosen role.
 */
final class CreateStaffMemberUseCase
{
    public function __construct(private readonly UserRepository $users) {}

    public function execute(CreateStaffMemberDTO $data): User
    {
        return $this->users->createWithRole($data->toArray(), $data->role);
    }
}
