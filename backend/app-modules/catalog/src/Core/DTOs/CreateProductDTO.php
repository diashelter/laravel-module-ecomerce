<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\DTOs;

final readonly class CreateProductDTO
{
    /**
     * @param  int  $stockQuantity  only used to create the initial Stock row
     */
    public function __construct(
        public ProductDTO $product,
        public int $stockQuantity,
    ) {}
}
