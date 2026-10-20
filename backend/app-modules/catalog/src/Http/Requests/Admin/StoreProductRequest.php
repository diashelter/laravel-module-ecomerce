<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Admin;

use Modules\Catalog\Core\DTOs\CreateProductDTO;

class StoreProductRequest extends ProductRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Only used to create the initial Stock row; later changes go through /admin/stocks.
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function toDto(): CreateProductDTO
    {
        return new CreateProductDTO(
            product: $this->productDto(),
            stockQuantity: (int) $this->validated('stock_quantity'),
        );
    }
}
