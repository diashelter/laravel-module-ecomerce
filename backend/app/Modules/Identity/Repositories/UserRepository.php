<?php

declare(strict_types=1);

namespace App\Modules\Identity\Repositories;

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    protected function model(): string
    {
        return User::class;
    }

    /**
     * `role` is not fillable, so it is assigned explicitly instead of through mass assignment.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createWithRole(array $attributes, UserRole $role): User
    {
        $user = new User($attributes);
        $user->role = $role;
        $user->save();

        return $user;
    }

    /** @return LengthAwarePaginator<int, User> */
    public function paginateNewestFirst(int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->latest()
            ->latest('id')
            ->paginate($perPage);
    }

    public function countCustomers(): int
    {
        return $this->query()->customers()->count();
    }
}
