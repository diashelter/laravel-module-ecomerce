<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Modules\Inventory\Core\DTOs\AdjustStockDTO;
use Modules\Inventory\Core\Enums\StockOperation;
use Modules\Shared\Http\Requests\ApiFormRequest;

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
