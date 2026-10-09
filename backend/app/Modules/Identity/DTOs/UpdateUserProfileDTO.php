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

    /**
     * Name and e-mail always change. The password only changes when a new one is sent.
     * The value objects become plain strings here, for the Eloquent attributes.
     *
     * @return array{name: string, email: string, password?: string}
     */
    public function toArray(): array
    {
        $changes = ['name' => $this->name, 'email' => $this->email->value()];

        if ($this->password !== null) {
            $changes['password'] = $this->password->reveal();
        }

        return $changes;
    }
}
