<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\DTOs\ProductDTO;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Product fields shared by StoreProductRequest and UpdateProductRequest.
 */
abstract class ProductRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'description' => ['required', 'string', 'max:5000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    protected function productDto(): ProductDTO
    {
        return new ProductDTO(
            name: $this->validated('name'),
            price: (string) $this->validated('price'),
            description: $this->validated('description'),
            imageUrl: $this->validated('image_url'),
            status: ProductStatus::from($this->validated('status')),
            categoryIds: array_map(intval(...), array_values($this->validated('category_ids'))),
        );
    }
}
