<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Contracts\ProductCatalog;
use App\Modules\Catalog\Repositories\ProductRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the contracts the catalog publishes to the other modules.
 */
class CatalogServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProductCatalog::class => ProductRepository::class,
    ];
}
