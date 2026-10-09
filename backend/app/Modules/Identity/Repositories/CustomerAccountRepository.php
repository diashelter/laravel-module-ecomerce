<?php

declare(strict_types=1);

namespace App\Modules\Identity\Repositories;

use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Services\UserService;
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
    public function __construct(private readonly UserService $userService) {}

    protected function model(): string
    {
        return CustomerAccount::class;
    }

    public function register(CreateUserDTO $data): CustomerProfile
    {
        return $this->profileOf($this->create($data->toArray()));
    }

    public function updateProfile(int $customerId, UpdateUserProfileDTO $data): CustomerProfile
    {
        $account = $this->query()->findOrFail($customerId);

        return $this->profileOf($this->update($account, $this->userService->profileChanges($data)));
    }

    public function findProfile(int $customerId): ?CustomerProfile
    {
        $account = $this->query()->find($customerId);

        return $account === null ? null : $this->profileOf($account);
    }

    /** @return LengthAwarePaginator<int, CustomerProfile> */
    public function paginateNewestFirst(int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (CustomerAccount $account) => $this->profileOf($account));
    }

    private function profileOf(CustomerAccount $account): CustomerProfile
    {
        return new CustomerProfile($account->id, $account->name, $account->email, $account->created_at->toImmutable());
    }
}
