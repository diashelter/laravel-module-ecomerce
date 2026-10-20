<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Modules\Catalog\Core\DTOs\ProductDTO;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Catalog\Core\ValueObjects\CategoryIds;
use Modules\Shared\Http\Requests\ApiFormRequest;
use Modules\Shared\Http\Rules\CanonicalRecordId;

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
            // `bail`: a malformed id must never reach `exists` (PostgreSQL would fail instead of finding no row).
            'category_ids.*' => ['bail', new CanonicalRecordId, 'distinct', 'exists:categories,id'],
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
            categoryIds: new CategoryIds(...array_values($this->validated('category_ids'))),
        );
    }
}
