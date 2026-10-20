<?php

declare(strict_types=1);

namespace Modules\Identity\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Core\DTOs\CreateUserDTO;
use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Identity\Core\ValueObjects\CustomerProfile;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Shared\Repositories\BaseRepository;

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

    public function updateProfile(string $customerId, UpdateUserProfileDTO $data): CustomerProfile
    {
        $account = $this->query()->findOrFail($customerId);

        return $this->update($account, $data->toArray())->toProfile();
    }

    public function findProfile(string $customerId): ?CustomerProfile
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
