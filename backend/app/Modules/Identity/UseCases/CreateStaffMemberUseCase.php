<?php

declare(strict_types=1);

namespace App\Modules\Identity\UseCases;

use App\Modules\Identity\DTOs\CreateStaffMemberDTO;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;

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
