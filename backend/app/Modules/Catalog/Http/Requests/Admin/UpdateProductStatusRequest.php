<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\DTOs\UpdateProductStatusDTO;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

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
