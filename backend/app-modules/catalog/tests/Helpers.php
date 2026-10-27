<?php

use Modules\Catalog\Core\Consumers\ProjectCategoryCreated;
use Modules\Catalog\Core\Consumers\ProjectCategoryDeleted;
use Modules\Catalog\Core\Consumers\ProjectCategoryUpdated;
use Modules\Catalog\Core\Consumers\ProjectProductCreated;
use Modules\Catalog\Core\Consumers\ProjectProductDeleted;
use Modules\Catalog\Core\Consumers\ProjectProductStatusChanged;
use Modules\Catalog\Core\Consumers\ProjectProductUpdated;
use Modules\Catalog\Core\Consumers\ProjectStockChanged;
use Modules\Catalog\Core\InboundEvents\StockChanged;
use Modules\Catalog\Events\CategoryChanged;
use Modules\Catalog\Events\CategoryCreated;
use Modules\Catalog\Events\CategoryDeleted;
use Modules\Catalog\Events\CategoryUpdated;
use Modules\Catalog\Events\ProductChanged;
use Modules\Catalog\Events\ProductCreated;
use Modules\Catalog\Events\ProductDeleted;
use Modules\Catalog\Events\ProductStatusChanged;
use Modules\Catalog\Events\ProductUpdated;
use Modules\Catalog\Persistence\ReadModel\ReadModelStore;
use Modules\Messaging\Contracts\DomainEvent;
use Modules\Messaging\Core\EventEnvelope;

/** The projector consumer of each storefront event. */
const STOREFRONT_CONSUMERS = [
    ProductCreated::class => ProjectProductCreated::class,
    ProductUpdated::class => ProjectProductUpdated::class,
    ProductStatusChanged::class => ProjectProductStatusChanged::class,
    ProductDeleted::class => ProjectProductDeleted::class,
    CategoryCreated::class => ProjectCategoryCreated::class,
    CategoryUpdated::class => ProjectCategoryUpdated::class,
    CategoryDeleted::class => ProjectCategoryDeleted::class,
    StockChanged::class => ProjectStockChanged::class,
];

function readModel(): ReadModelStore
{
    return app(ReadModelStore::class);
}

/** MongoDB is not rolled back by RefreshDatabase: every feature test starts with both collections empty. */
function emptyReadModel(): void
{
    readModel()->products()->deleteMany([]);
    readModel()->categories()->deleteMany([]);
}

/** @return array<string, mixed>|null the stored document, or null */
function productDocument(string $id): ?array
{
    return readModel()->products()->findOne(['_id' => $id], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
}

/** @return array<string, mixed>|null the stored document, or null */
function categoryDocument(string $id): ?array
{
    return readModel()->categories()->findOne(['_id' => $id], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
}

/**
 * A product event with the given overrides, by constructor name (productId, name, priceCents, description, imageUrl,
 * status, categoryIds, createdAt, updatedAt, version).
 *
 * @param  class-string<ProductChanged>  $event
 */
function productEvent(string $id, int $version, string $event = ProductUpdated::class, array $overrides = []): ProductChanged
{
    return new $event(...[
        'productId' => $id,
        'name' => 'Monitor 27',
        'priceCents' => 129990,
        'description' => 'Monitor IPS',
        'imageUrl' => 'https://example.com/monitor.png',
        'status' => 'active',
        'categoryIds' => [],
        'createdAt' => '2026-10-27T10:00:00+00:00',
        'updatedAt' => '2026-10-27T11:00:00+00:00',
        'version' => $version,
        ...$overrides,
    ]);
}

function stockEvent(string $productId, int $quantity, int $version, ?string $stockId = null): StockChanged
{
    return new StockChanged($stockId ?? '0199f0a0-0000-7000-8000-0000000000e1', $productId, $quantity, $version);
}

/** @param  class-string<CategoryChanged>  $event */
function categoryEvent(string $id, int $version, string $name = 'Eletrônicos', string $slug = 'eletronicos', string $event = CategoryUpdated::class): CategoryChanged
{
    return new $event($id, $name, $slug, $version);
}

/** One delivery of the event to its projector, through the rules both transports share. */
function project(DomainEvent $event): EventEnvelope
{
    $envelope = envelopeOf($event);
    deliverEnvelope($envelope, STOREFRONT_CONSUMERS[$event::class]);

    return $envelope;
}

/** The same envelope delivered again to the projector of its event. */
function redeliver(EventEnvelope $envelope, string $event): void
{
    deliverEnvelope($envelope, STOREFRONT_CONSUMERS[$event]);
}
