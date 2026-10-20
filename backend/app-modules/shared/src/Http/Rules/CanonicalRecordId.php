<?php

declare(strict_types=1);

namespace Modules\Shared\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Shared\Support\RecordId;

/**
 * An id in the body must be a canonical UUID (see RecordId). Put it before any `exists` rule with
 * `bail`: PostgreSQL answers a malformed value compared with a `uuid` column with an error, not
 * with "no rows".
 */
final class CanonicalRecordId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! RecordId::isCanonical($value)) {
            $fail('validation.uuid')->translate();
        }
    }
}
