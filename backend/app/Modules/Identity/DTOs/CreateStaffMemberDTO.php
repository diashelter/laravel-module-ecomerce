<?php

declare(strict_types=1);

namespace App\Modules\Identity\DTOs;

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;

final readonly class CreateStaffMemberDTO
{
    public function __construct(
        public string $name,
        public Email $email,
        public Password $password,
        public UserRole $role,
    ) {}

    /**
     * The value objects become plain strings here, for the Eloquent attributes. The role is
     * not an attribute: it is assigned explicitly by the repository.
     *
     * @return array{name: string, email: string, password: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'email' => $this->email->value(), 'password' => $this->password->reveal()];
    }
}
