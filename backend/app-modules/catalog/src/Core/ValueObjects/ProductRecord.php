<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\ValueObjects;

use Carbon\CarbonImmutable;
use Modules\Catalog\Core\Enums\ProductStatus;

/**
 * What a product write leaves behind: the data of the row it wrote, never the model. The read side
 * completes it with what lives in other tables (see ProductQuery::complete) without reading the
 * product again.
 */
final readonly class ProductRecord
{
    public function __construct(
        public string $id,
        public string $name,
        public int $priceCents,
        public string $description,
        public ?string $imageUrl,
        public ProductStatus $status,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}
}
