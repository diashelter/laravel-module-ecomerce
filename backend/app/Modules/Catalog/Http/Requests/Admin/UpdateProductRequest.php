<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\DTOs\ProductDTO;

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
