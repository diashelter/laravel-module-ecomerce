<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Customers\Repositories\CustomerAddressRepository;
use App\Modules\Ordering\Contracts\DeliveryAddressBook;
use Illuminate\Support\ServiceProvider;

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
