<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Admin;

use Modules\Catalog\Core\DTOs\ProductDTO;

/**
 * Updates product data only. Stock is managed separately (StockController).
 */
class UpdateProductRequest extends ProductRequest
{
    public function toDto(): ProductDTO
    {
        return $this->productDto();
    }
}
