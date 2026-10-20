<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\DTOs;

use Modules\Catalog\Core\Enums\ProductStatus;

final readonly class UpdateProductStatusDTO
{
    public function __construct(public ProductStatus $status) {}
}
