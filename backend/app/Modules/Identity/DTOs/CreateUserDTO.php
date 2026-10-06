<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

/**
 * There is deliberately no role here: the role is decided by the service, never by the input.
 */
final readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    /** @return array{name: string, email: string, password: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'email' => $this->email, 'password' => $this->password];
    }
}
