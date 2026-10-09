<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests\Concerns;

use App\Modules\Identity\ValueObjects\Email;

/**
 * Normalizes `email` before validation, so `unique` and the stored value use the canonical form
 * that `Email` decides. The customers module's own copy: Identity's Http is private.
 */
trait NormalizesEmailInput
{
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Email::normalize($email)]);
        }
    }
}
