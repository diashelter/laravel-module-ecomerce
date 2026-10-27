<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\Consumers;

use Modules\Catalog\Events\CategoryCreated;
use Modules\Catalog\Persistence\Repositories\CategoryDocumentRepository;
use Modules\Messaging\Contracts\EventConsumer;

/**
 * Consumer (catalog, projector): catalog.category_created -> the category document.
 * The version of the event is the natural key of idempotency (AD-001): a repeated or late delivery matches
 * nothing in the database and is discarded.
 */
class ProjectCategoryCreated implements EventConsumer
{
    public function __construct(private readonly CategoryDocumentRepository $documents) {}

    public function handle(CategoryCreated $event): void
    {
        $this->documents->replace($event->categoryId, $event->name, $event->slug, $event->version);
    }
}
