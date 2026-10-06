<?php

declare(strict_types=1);

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\ProductStatus;

final readonly class UpdateProductStatusDTO
{
    public function __construct(public ProductStatus $status) {}
}
