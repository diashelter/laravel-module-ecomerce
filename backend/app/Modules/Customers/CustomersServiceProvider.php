<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Customers\Repositories\CustomerAddressRepository;
use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Ordering\Contracts\DeliveryAddressBook;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the contracts the ordering module defines and the customers module implements, and resolves
 * the {customer} of the admin routes through identity's CustomerAccounts contract (404 when unknown).
 */
class CustomersServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DeliveryAddressBook::class => CustomerAddressRepository::class,
    ];

    public function boot(): void
    {
        Route::bind('customer', fn (string $id): CustomerProfile => $this->app->make(CustomerAccounts::class)
            ->findProfile((int) $id) ?? abort(404));
    }
}
