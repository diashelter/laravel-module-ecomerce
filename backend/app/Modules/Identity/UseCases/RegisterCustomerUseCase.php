<?php

declare(strict_types=1);

namespace App\Modules\Identity\UseCases;

use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Repositories\CustomerAccountRepository;

/**
 * Visitor: creates their own shopper account.
 */
final class RegisterCustomerUseCase
{
    public function __construct(private readonly CustomerAccountRepository $accounts) {}

    public function execute(CreateUserDTO $data): CustomerAccount
    {
        return $this->accounts->create($data->toArray());
    }
}
