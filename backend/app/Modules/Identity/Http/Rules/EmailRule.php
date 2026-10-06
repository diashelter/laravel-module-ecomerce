<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Rules;

use App\Modules\Identity\ValueObjects\Email;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Delegates the e-mail rules to the `Email` value object, so they are written only once.
 * Non-strings are left to the `string` rule.
 */
final class EmailRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $normalized = Email::normalize($value);

        if (Email::isTooLong($normalized)) {
            $fail('validation.max.string')->translate(['max' => Email::MAX_LENGTH]);

            return;
        }

        if (! Email::isWellFormed($normalized)) {
            $fail('validation.email')->translate();
        }
    }
}
