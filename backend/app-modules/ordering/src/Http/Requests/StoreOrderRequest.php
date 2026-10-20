<?php

declare(strict_types=1);

namespace Modules\Ordering\Http\Requests;

use Modules\Shared\Http\Rules\CanonicalRecordId;

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
            // `bail`: a malformed id never reaches the address book or the stock.
            'address_id' => ['bail', 'required', new CanonicalRecordId],
        ];
    }

    public function messages(): array
    {
        return [
            'address_id.required' => 'Escolha um endereço de entrega.',
        ];
    }

    public function addressId(): string
    {
        return $this->validated('address_id');
    }
}
