<?php

declare(strict_types=1);

namespace App\Modules\Identity\Repositories;

use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Also answers the CustomerAccounts contract other modules use (bound in IdentityServiceProvider).
 *
 * @extends BaseRepository<CustomerAccount>
 */
class CustomerAccountRepository extends BaseRepository implements CustomerAccounts
{
    protected function model(): string
    {
        return CustomerAccount::class;
    }

    public function register(CreateUserDTO $data): CustomerProfile
    {
        return $this->create($data->toArray())->toProfile();
    }

    public function updateProfile(int $customerId, UpdateUserProfileDTO $data): CustomerProfile
    {
        $account = $this->query()->findOrFail($customerId);

        return $this->update($account, $data->toArray())->toProfile();
    }

    public function findProfile(int $customerId): ?CustomerProfile
    {
        return $this->query()->find($customerId)?->toProfile();
    }

    /** @return LengthAwarePaginator<int, CustomerProfile> */
    public function paginateNewestFirst(int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (CustomerAccount $account) => $account->toProfile());
    }
}
