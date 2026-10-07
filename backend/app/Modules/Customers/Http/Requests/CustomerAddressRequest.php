<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\DTOs\CustomerAddressDTO;
use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\Http\Requests\Concerns\NormalizesStateInput;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Used for both create and update: the update replaces the whole address, so the required
 * fields are the same.
 */
class CustomerAddressRequest extends ApiFormRequest
{
    use NormalizesStateInput;

    /**
     * Creating needs no address. Updating is for the owner only, and is decided before the
     * body is validated, so a stranger never learns what is wrong with it.
     */
    public function authorize(): bool
    {
        $address = $this->route('address');

        return $address === null || Gate::allows('update', $address);
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:120'],
            // 8 digits, with or without the hyphen: "01310100" or "01310-100".
            'postal_code' => ['required', 'string', 'regex:/^(\d{8}|\d{5}-\d{3})$/'],
            'street' => ['required', 'string', 'max:150'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', Rule::enum(BrazilianState::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'postal_code.regex' => 'O CEP deve ter 8 dígitos, com ou sem hífen (por exemplo 01310-100).',
        ];
    }

    public function toDto(): CustomerAddressDTO
    {
        return new CustomerAddressDTO(
            recipientName: $this->validated('recipient_name'),
            postalCode: str_replace('-', '', $this->validated('postal_code')),
            street: $this->validated('street'),
            number: $this->validated('number'),
            complement: $this->validated('complement'),
            district: $this->validated('district'),
            city: $this->validated('city'),
            state: BrazilianState::from($this->validated('state')),
        );
    }
}
