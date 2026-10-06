<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\DTOs\ProductCatalogFilterDTO;
use App\Modules\Shared\Http\Requests\ApiFormRequest;

class ProductIndexRequest extends ApiFormRequest
{
    public const SORTS = ['name', 'price_asc', 'price_desc'];

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDto(): ProductCatalogFilterDTO
    {
        return new ProductCatalogFilterDTO(
            category: $this->validated('category'),
            sort: $this->validated('sort') ?? 'name',
        );
    }
}
