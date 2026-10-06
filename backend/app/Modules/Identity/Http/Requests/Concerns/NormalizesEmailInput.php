<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Concerns;

use App\Modules\Identity\ValueObjects\Email;

/**
 * Normalizes `email` before validation, so `unique` and the stored value use the canonical form.
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
