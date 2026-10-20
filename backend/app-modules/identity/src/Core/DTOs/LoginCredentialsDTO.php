<?php

declare(strict_types=1);

namespace Modules\Identity\Core\DTOs;

use Modules\Identity\Core\ValueObjects\Email;

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
