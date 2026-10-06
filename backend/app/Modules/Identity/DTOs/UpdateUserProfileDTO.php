<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

final readonly class UpdateUserProfileDTO
{
    /**
     * @param  string|null  $password  null keeps the current password
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
    ) {}
}
