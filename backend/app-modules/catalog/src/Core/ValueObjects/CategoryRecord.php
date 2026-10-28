<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\ValueObjects;

/** The data of a category row, as the republication reads it. */
final readonly class CategoryRecord
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public int $version,
    ) {}
}
