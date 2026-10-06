<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\UpdateUserProfileDTO;

class UserService
{
    /**
     * Name and e-mail always change. The password only changes when a new one is sent.
     *
     * @return array{name: string, email: string, password?: string}
     */
    public function profileChanges(UpdateUserProfileDTO $data): array
    {
        $changes = ['name' => $data->name, 'email' => $data->email->value()];

        if ($data->password !== null) {
            $changes['password'] = $data->password->reveal();
        }

        return $changes;
    }
}
