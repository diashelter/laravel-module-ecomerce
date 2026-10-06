<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\ValueObjects\Email;

/**
 * The password is plain text on purpose: the login must accept passwords chosen under an older policy.
 */
final readonly class LoginCredentialsDTO
{
    public function __construct(
        public Email $email,
        public string $password,
    ) {}

    /** @return array{email: string, password: string} */
    public function toArray(): array
    {
        return ['email' => $this->email->value(), 'password' => $this->password];
    }
}
