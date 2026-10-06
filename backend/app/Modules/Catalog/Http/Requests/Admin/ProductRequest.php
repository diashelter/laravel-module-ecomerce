<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\DTOs\ProductDTO;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\ValueObjects\CategoryIds;
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
            'price_cents' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'description' => ['required', 'string', 'max:5000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    /** The limit is typed in reais by the admin, so the cents bound is not shown as is. */
    public function messages(): array
    {
        return [
            'price_cents.max' => 'O preço não pode ser maior que R$ 99.999.999,99.',
        ];
    }

    protected function productDto(): ProductDTO
    {
        return new ProductDTO(
            name: $this->validated('name'),
            priceCents: (int) $this->validated('price_cents'),
            description: $this->validated('description'),
            imageUrl: $this->validated('image_url'),
            status: ProductStatus::from($this->validated('status')),
            categoryIds: new CategoryIds(...array_map(intval(...), array_values($this->validated('category_ids')))),
        );
    }
}
