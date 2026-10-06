<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;

/**
 * There is deliberately no role here: the role is decided by the service, never by the input.
 */
final readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public Email $email,
        public Password $password,
    ) {}

    /**
     * The value objects become plain strings here, for the Eloquent attributes.
     *
     * @return array{name: string, email: string, password: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'email' => $this->email->value(), 'password' => $this->password->reveal()];
    }
}
