<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\Consumers;

use Modules\Catalog\Events\CategoryDeleted;
use Modules\Catalog\Persistence\Repositories\CategoryDocumentRepository;
use Modules\Messaging\Contracts\EventConsumer;

/**
 * Consumer (catalog, projector): catalog.category_deleted -> the tombstone of the category document.
 * The version of the event is the natural key of idempotency (AD-001): a repeated or late delivery matches
 * nothing in the database and is discarded.
 */
class ProjectCategoryDeleted implements EventConsumer
{
    public function __construct(private readonly CategoryDocumentRepository $documents) {}

    public function handle(CategoryDeleted $event): void
    {
        $this->documents->tombstone($event->categoryId, $event->version);
    }
}
