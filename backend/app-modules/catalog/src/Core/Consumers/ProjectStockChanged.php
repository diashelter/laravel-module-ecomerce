<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\Consumers;

use Modules\Catalog\Core\InboundEvents\StockChanged;
use Modules\Catalog\Persistence\Repositories\ProductDocumentRepository;
use Modules\Messaging\Contracts\EventConsumer;

/**
 * Consumer (catalog, projector): inventory.stock_changed -> the stock part of the storefront document.
 * A stock that arrives before its product creates the document with only that part.
 * The version of the event is the natural key of idempotency (AD-001): a repeated or late delivery matches
 * nothing in the database and is discarded.
 */
class ProjectStockChanged implements EventConsumer
{
    public function __construct(private readonly ProductDocumentRepository $documents) {}

    public function handle(StockChanged $event): void
    {
        $this->documents->replaceStockPart($event->productId, [
            'id' => $event->stockId,
            'quantity' => $event->quantity,
            'version' => $event->version,
        ]);
    }
}
