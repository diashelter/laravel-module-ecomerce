<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\Consumers;

use Modules\Catalog\Core\Services\ProductPartFactory;
use Modules\Catalog\Events\ProductStatusChanged;
use Modules\Catalog\Persistence\Repositories\ProductDocumentRepository;
use Modules\Messaging\Contracts\EventConsumer;

/**
 * Consumer (catalog, projector): catalog.product_status_changed -> the product part of the storefront document.
 * The version of the event is the natural key of idempotency (AD-001): a repeated or late delivery matches
 * nothing in the database and is discarded.
 */
class ProjectProductStatusChanged implements EventConsumer
{
    public function __construct(private readonly ProductDocumentRepository $documents, private readonly ProductPartFactory $parts) {}

    public function handle(ProductStatusChanged $event): void
    {
        $this->documents->replaceProductPart($event->productId, $this->parts->from($event));
    }
}
