<?php

declare(strict_types=1);

namespace Modules\Customers\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Customers\Persistence\Repositories\CustomerAddressRepository;
use Modules\Ordering\Contracts\DeliveryAddressBook;
use Modules\Shared\Support\RecordId;

/**
 * Wires the contracts the ordering module defines and the customers module implements.
 */
class CustomersServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        DeliveryAddressBook::class => CustomerAddressRepository::class,
    ];

    public function register(): void
    {
        // The route parameters this module owns are canonical UUIDs (see CatalogServiceProvider::register()).
        foreach (['customer', 'address'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }
    }
}
