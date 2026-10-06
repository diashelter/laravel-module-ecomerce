<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests;

use App\Modules\Ordering\DTOs\CartDTO;
use App\Modules\Ordering\DTOs\CartItemDTO;
use App\Modules\Shared\Http\Requests\ApiFormRequest;

/**
 * The client only sends product ids and quantities. Prices, names and totals sent by
 * the client are never used: everything is recalculated from the database.
 */
class ValidateCartRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function toDto(): CartDTO
    {
        return new CartDTO(array_map(
            fn (array $item) => new CartItemDTO((int) $item['product_id'], (int) $item['quantity']),
            array_values($this->validated('items')),
        ));
    }
}
