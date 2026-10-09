<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Published by identity for the customers module: manages the shoppers' accounts and answers
 * with their profile, never with the model. The account rules (normalized e-mail, hashed
 * password, a password that changes only when a new one is sent) stay here.
 */
interface CustomerAccounts
{
    public function register(CreateUserDTO $data): CustomerProfile;

    public function updateProfile(int $customerId, UpdateUserProfileDTO $data): CustomerProfile;

    /**
     * Null when there is no such account.
     */
    public function findProfile(int $customerId): ?CustomerProfile;

    /** @return LengthAwarePaginator<int, CustomerProfile> */
    public function paginateNewestFirst(int $perPage): LengthAwarePaginator;
}
