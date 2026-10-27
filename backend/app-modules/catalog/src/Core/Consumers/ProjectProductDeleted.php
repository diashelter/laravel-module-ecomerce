<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\Consumers;

use Modules\Catalog\Events\ProductDeleted;
use Modules\Catalog\Persistence\Repositories\ProductDocumentRepository;
use Modules\Messaging\Contracts\EventConsumer;

/**
 * Consumer (catalog, projector): catalog.product_deleted -> the tombstone of the storefront document.
 * The version of the event is the natural key of idempotency (AD-001): a repeated or late delivery matches
 * nothing in the database and is discarded.
 */
class ProjectProductDeleted implements EventConsumer
{
    public function __construct(private readonly ProductDocumentRepository $documents) {}

    public function handle(ProductDeleted $event): void
    {
        $this->documents->tombstone($event->productId, $event->version);
    }
}
