<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Modules\Catalog\Core\DTOs\UpdateProductStatusDTO;
use Modules\Catalog\Core\Enums\ProductStatus;
use Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateProductStatusRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ];
    }

    public function toDto(): UpdateProductStatusDTO
    {
        return new UpdateProductStatusDTO(ProductStatus::from($this->validated('status')));
    }
}
