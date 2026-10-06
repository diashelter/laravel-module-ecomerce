<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;

final readonly class UpdateUserProfileDTO
{
    /**
     * @param  Password|null  $password  null keeps the current password
     */
    public function __construct(
        public string $name,
        public Email $email,
        public ?Password $password = null,
    ) {}
}
