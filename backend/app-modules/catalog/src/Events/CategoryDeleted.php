<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

use Modules\Messaging\Contracts\DomainEvent;

/** Catalog: a category was deleted. The version is the stored one plus 1, so the projector leaves a tombstone. */
final class CategoryDeleted implements DomainEvent
{
    public function __construct(public string $categoryId, public int $version) {}

    public static function eventName(): string
    {
        return 'catalog.category_deleted';
    }

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return ['category_id' => $this->categoryId, 'version' => $this->version];
    }

    public static function fromPayload(array $payload): static
    {
        return new self($payload['category_id'], $payload['version']);
    }
}
