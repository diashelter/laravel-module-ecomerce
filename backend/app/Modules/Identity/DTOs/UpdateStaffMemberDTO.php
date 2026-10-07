<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\UserRole;

final readonly class UpdateStaffMemberDTO
{
    public function __construct(
        public UpdateUserProfileDTO $profile,
        public UserRole $role,
    ) {}
}
