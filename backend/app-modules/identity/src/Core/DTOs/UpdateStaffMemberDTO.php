<?php

declare(strict_types=1);

namespace Modules\Identity\Core\DTOs;

use Modules\Identity\Core\Enums\UserRole;

final readonly class UpdateStaffMemberDTO
{
    public function __construct(
        public UpdateUserProfileDTO $profile,
        public UserRole $role,
    ) {}
}
