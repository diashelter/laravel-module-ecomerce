<?php

declare(strict_types=1);

namespace App\Modules\Identity\Repositories;

use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<CustomerAccount>
 */
class CustomerAccountRepository extends BaseRepository
{
    protected function model(): string
    {
        return CustomerAccount::class;
    }

    /** @return LengthAwarePaginator<int, CustomerAccount> */
    public function paginateNewestFirst(int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->latest()
            ->latest('id')
            ->paginate($perPage);
    }
}
