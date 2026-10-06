<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Rules;

use App\Modules\Identity\ValueObjects\Password;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Delegates the password policy to the `Password` value object, so it is written only once.
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
