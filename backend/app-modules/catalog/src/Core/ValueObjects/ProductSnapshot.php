<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\ValueObjects;

/** A product row and the ids of its categories (ascending), read together in one snapshot of the database. */
final readonly class ProductSnapshot
{
    /** @param  list<string>  $categoryIds */
    public function __construct(
        public ProductRecord $product,
        public array $categoryIds,
    ) {}
}
