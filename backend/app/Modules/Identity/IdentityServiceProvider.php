<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\Repositories\CustomerAccountRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the contracts identity publishes to the other modules.
 */
class IdentityServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CustomerAccounts::class => CustomerAccountRepository::class,
    ];
}
