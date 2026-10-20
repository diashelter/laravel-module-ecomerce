<?php

declare(strict_types=1);

namespace Modules\Identity\Core\UseCases;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Identity\Persistence\Models\User;
use Modules\Identity\Persistence\Repositories\UserRepository;

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
