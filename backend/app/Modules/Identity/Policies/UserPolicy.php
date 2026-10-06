<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;

class UserPolicy
{
    /** Administrators are never edited through the customers screen. */
    public function update(User $actor, User $target): bool
    {
        return ! $target->isAdmin();
    }
}
