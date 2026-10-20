<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Identity\Core\ValueObjects\Password;

/**
 * The customers module's own adapter of the `Password` value object (the Http folder of a module
 * is private, so Identity keeps its own). It only translates: the policy lives in `Password`.
 * Non-strings are left to the `string` rule.
 */
final class PasswordRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! Password::isLongEnough($value)) {
            $fail('validation.min.string')->translate(['min' => Password::MIN_LENGTH]);
        }
    }
}
