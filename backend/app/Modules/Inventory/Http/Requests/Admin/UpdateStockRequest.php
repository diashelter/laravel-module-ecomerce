<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Admin;

use App\Modules\Inventory\DTOs\AdjustStockDTO;
use App\Modules\Inventory\Enums\StockOperation;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateStockRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::enum(StockOperation::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function toDto(): AdjustStockDTO
    {
        return new AdjustStockDTO(
            operation: StockOperation::from($this->validated('operation')),
            quantity: (int) $this->validated('quantity'),
        );
    }
}
