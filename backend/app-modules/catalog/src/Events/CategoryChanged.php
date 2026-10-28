<?php

declare(strict_types=1);

namespace Modules\Catalog\Events;

use Modules\Messaging\Contracts\DomainEvent;

/** What the two category events share: the category as the row holds it after the write, with its version. */
abstract class CategoryChanged implements DomainEvent
{
    final public function __construct(
        public string $categoryId,
        public string $name,
        public string $slug,
        public int $version,
    ) {}

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'slug' => $this->slug,
            'version' => $this->version,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new static($payload['category_id'], $payload['name'], $payload['slug'], $payload['version']);
    }
}
