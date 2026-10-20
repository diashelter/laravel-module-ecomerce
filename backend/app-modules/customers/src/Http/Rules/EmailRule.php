<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Identity\Core\ValueObjects\Email;

/**
 * The customers module's own adapter of the `Email` value object (the Http folder of a module is
 * private, so Identity keeps its own). It only translates: the e-mail rules live in `Email`.
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
