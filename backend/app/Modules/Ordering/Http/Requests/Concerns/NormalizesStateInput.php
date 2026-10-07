<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests\Concerns;

use App\Modules\Ordering\Enums\BrazilianState;

/**
 * Normalizes `state` before validation, so " sp " is read as "SP" by the address book and the
 * shipping quote alike.
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
