<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Http\Requests\Concerns;

use App\Modules\Ordering\Enums\BrazilianState;

/**
 * Normalizes `state` before validation, so " sp " is read as "SP": the canonical form is the one
 * `BrazilianState` decides. The fulfillment module's own copy: Ordering's Http is private.
 */
trait NormalizesStateInput
{
    protected function prepareForValidation(): void
    {
        $state = $this->input('state');

        if (is_string($state)) {
            $this->merge(['state' => BrazilianState::normalize($state)]);
        }
    }
}
