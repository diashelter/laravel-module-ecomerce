<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Fulfillment\Http\Requests\Concerns\NormalizesStateInput;
use Modules\Ordering\Core\Enums\BrazilianState;
use Modules\Shared\Http\Requests\ApiFormRequest;

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
