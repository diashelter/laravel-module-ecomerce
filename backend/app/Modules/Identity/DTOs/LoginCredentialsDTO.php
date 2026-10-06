<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

final readonly class LoginCredentialsDTO
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /** @return array{email: string, password: string} */
    public function toArray(): array
    {
        return ['email' => $this->email, 'password' => $this->password];
    }
}
