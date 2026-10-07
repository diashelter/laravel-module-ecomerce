<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests;

/**
 * The cart payload plus the delivery address, chosen from the customer's own address book.
 * Who may place an order is decided by the `auth:customer` route group: every shopper account
 * can, and staff have no store session. The shipping and the totals are never sent: the server
 * calculates them.
 */
class StoreOrderRequest extends ValidateCartRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'address_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'address_id.required' => 'Escolha um endereço de entrega.',
        ];
    }

    public function addressId(): int
    {
        return (int) $this->validated('address_id');
    }
}
