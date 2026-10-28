<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Modules\Catalog\Core\DTOs\ProductCatalogFilterDTO;
use Modules\Shared\Http\Requests\ApiFormRequest;

class ProductIndexRequest extends ApiFormRequest
{
    public const SORTS = ['name', 'price_asc', 'price_desc'];

    /** Keeps (page - 1) * per page an int: a larger page is a 422, not a skip the database driver refuses. */
    public const MAX_PAGE = 1_000_000;

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
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
