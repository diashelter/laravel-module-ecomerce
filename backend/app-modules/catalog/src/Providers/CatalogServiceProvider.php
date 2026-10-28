<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Console\ClearReadModelCommand;
use Modules\Catalog\Console\RepublishCatalogCommand;
use Modules\Catalog\Contracts\CatalogMetrics;
use Modules\Catalog\Contracts\ProductCatalog;
use Modules\Catalog\Core\Consumers\ProjectCategoryCreated;
use Modules\Catalog\Core\Consumers\ProjectCategoryDeleted;
use Modules\Catalog\Core\Consumers\ProjectCategoryUpdated;
use Modules\Catalog\Core\Consumers\ProjectProductCreated;
use Modules\Catalog\Core\Consumers\ProjectProductDeleted;
use Modules\Catalog\Core\Consumers\ProjectProductStatusChanged;
use Modules\Catalog\Core\Consumers\ProjectProductUpdated;
use Modules\Catalog\Core\Consumers\ProjectStockChanged;
use Modules\Catalog\Core\InboundEvents\StockChanged;
use Modules\Catalog\Events\CategoryCreated;
use Modules\Catalog\Events\CategoryDeleted;
use Modules\Catalog\Events\CategoryUpdated;
use Modules\Catalog\Events\ProductCreated;
use Modules\Catalog\Events\ProductDeleted;
use Modules\Catalog\Events\ProductStatusChanged;
use Modules\Catalog\Events\ProductUpdated;
use Modules\Catalog\Persistence\Queries\CatalogMetricsQuery;
use Modules\Catalog\Persistence\Queries\ProductCatalogQuery;
use Modules\Messaging\Contracts\EventSubscriptions;
use Modules\Shared\Support\RecordId;

/**
 * Wires the contracts the catalog publishes to the other modules (both only read, so a query of the catalog answers
 * them) and subscribes the eight consumers of the storefront projector to the events they project.
 */
class CatalogServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CatalogMetrics::class => CatalogMetricsQuery::class,
        ProductCatalog::class => ProductCatalogQuery::class,
    ];

    public function register(): void
    {
        // Route parameters are canonical UUIDs: "/api/products/abc" returns 404 before any query, instead of a database
        // error (PostgreSQL refuses a non-UUID compared with a `uuid` column). The pattern is set here and not in boot():
        // a route copies the global patterns when it is created, and the module library creates the routes of a module
        // before the boot() of the providers. Each module sets the patterns of the parameters it owns.
        foreach (['product', 'category'] as $parameter) {
            Route::pattern($parameter, RecordId::PATTERN);
        }
    }

    public function boot(EventSubscriptions $subscriptions): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ClearReadModelCommand::class, RepublishCatalogCommand::class]);
        }

        // The stable names name the RabbitMQ queues of the consumers: they never derive from the classes.
        $subscriptions->subscribe(ProductCreated::class, ProjectProductCreated::class, 'catalog.project_product_created');
        $subscriptions->subscribe(ProductUpdated::class, ProjectProductUpdated::class, 'catalog.project_product_updated');
        $subscriptions->subscribe(ProductStatusChanged::class, ProjectProductStatusChanged::class, 'catalog.project_product_status_changed');
        $subscriptions->subscribe(ProductDeleted::class, ProjectProductDeleted::class, 'catalog.project_product_deleted');
        $subscriptions->subscribe(CategoryCreated::class, ProjectCategoryCreated::class, 'catalog.project_category_created');
        $subscriptions->subscribe(CategoryUpdated::class, ProjectCategoryUpdated::class, 'catalog.project_category_updated');
        $subscriptions->subscribe(CategoryDeleted::class, ProjectCategoryDeleted::class, 'catalog.project_category_deleted');
        $subscriptions->subscribe(StockChanged::class, ProjectStockChanged::class, 'catalog.project_stock_changed');
    }
}
