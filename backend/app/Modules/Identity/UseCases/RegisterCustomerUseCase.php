<?php

declare(strict_types=1);

namespace App\Modules\Identity\UseCases;

use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;

/**
 * Visitor: creates their own account. `role` is not fillable and defaults to "customer".
 */
final class RegisterCustomerUseCase
{
    public function __construct(private readonly UserRepository $users) {}

    public function execute(CreateUserDTO $data): User
    {
        return $this->users->create($data->toArray());
    }
}
