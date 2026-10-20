<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\DTOs;

final readonly class ProductCatalogFilterDTO
{
    /**
     * @param  string|null  $category  category slug
     * @param  string  $sort  one of ProductIndexRequest::SORTS
     */
    public function __construct(
        public ?string $category = null,
        public string $sort = 'name',
    ) {}
}
