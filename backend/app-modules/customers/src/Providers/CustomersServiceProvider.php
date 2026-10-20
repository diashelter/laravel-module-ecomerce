<?php

declare(strict_types=1);

namespace Modules\Customers\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Customers\Persistence\Repositories\CustomerAddressRepository;
use Modules\Ordering\Contracts\DeliveryAddressBook;

/**
 * Wires the contracts the ordering module defines and the customers module implements.
 */
class CustomersServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DeliveryAddressBook::class => CustomerAddressRepository::class,
    ];
}
