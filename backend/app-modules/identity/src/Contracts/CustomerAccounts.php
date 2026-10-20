<?php

declare(strict_types=1);

namespace Modules\Identity\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Identity\Core\DTOs\CreateUserDTO;
use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Identity\Core\ValueObjects\CustomerProfile;

/**
 * Published by identity for the customers module: manages the shoppers' accounts and answers
 * with their profile, never with the model. The account rules (normalized e-mail, hashed
 * password, a password that changes only when a new one is sent) stay here.
 */
interface CustomerAccounts
{
    public function register(CreateUserDTO $data): CustomerProfile;

    public function updateProfile(string $customerId, UpdateUserProfileDTO $data): CustomerProfile;

    /**
     * Null when there is no such account.
     */
    public function findProfile(string $customerId): ?CustomerProfile;

    /** @return LengthAwarePaginator<int, CustomerProfile> */
    public function paginateNewestFirst(int $perPage): LengthAwarePaginator;
}
