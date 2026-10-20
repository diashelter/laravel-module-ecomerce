<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Requests;

use Modules\Payment\Core\DTOs\PayOrderDTO;
use Modules\Shared\Http\Requests\ApiFormRequest;

class PayOrderRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // 64 is the size of payments.card_token; which tokens exist is up to the gateway.
        return [
            'card_token' => ['required', 'string', 'max:64'],
        ];
    }

    public function toDto(): PayOrderDTO
    {
        return new PayOrderDTO($this->string('card_token')->toString());
    }
}
