<?php

declare(strict_types=1);

namespace App\Modules\Identity\UseCases;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Admin: lists the staff members, newest first.
 */
final class ListStaffMembersUseCase
{
    public function __construct(private readonly UserRepository $users) {}

    /** @return LengthAwarePaginator<int, User> */
    public function execute(int $perPage): LengthAwarePaginator
    {
        return $this->users->paginateNewestFirst($perPage);
    }
}
