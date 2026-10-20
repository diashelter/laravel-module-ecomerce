<?php

declare(strict_types=1);

namespace Modules\Identity\Core\UseCases;

use Modules\Identity\Core\DTOs\CreateUserDTO;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Identity\Persistence\Repositories\CustomerAccountRepository;

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
