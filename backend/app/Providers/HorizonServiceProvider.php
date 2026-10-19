<?php

declare(strict_types=1);

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * The Horizon dashboard (/horizon) for the Laravel queue. Who may open it is decided by the `admin`
 * alias in config/horizon.php (`middleware`), the same gate as the staff management: only a staff
 * member with the `admin` role. Horizon's own gate would open it to everyone in `local` and, anywhere
 * else, check the default guard, which is not the `staff` one.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(fn () => true);
    }
}
