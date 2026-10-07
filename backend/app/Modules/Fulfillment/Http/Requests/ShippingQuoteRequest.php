<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Http\Requests;

use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\Http\Requests\Concerns\NormalizesStateInput;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * The only input of a quote is the destination state, in the query string.
 */
class ShippingQuoteRequest extends ApiFormRequest
{
    use NormalizesStateInput;

    public function rules(): array
    {
        return [
            'state' => ['required', 'string', Rule::enum(BrazilianState::class)],
        ];
    }

    public function toDto(): BrazilianState
    {
        return BrazilianState::from($this->validated('state'));
    }
}
