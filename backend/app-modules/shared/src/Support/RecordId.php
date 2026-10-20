<?php

declare(strict_types=1);

namespace Modules\Shared\Support;

/**
 * The canonical form of a record id: a UUID in lowercase hexadecimal with hyphens (8-4-4-4-12).
 * The one place that spells it out. The route patterns, the validation of the ids in the request
 * body and the typed id lists all read it from here.
 *
 * Uppercase is refused on purpose: ids are compared as text (the own account on a delete, the
 * keys of the quantity maps), so one record must never have two spellings.
 */
final class RecordId
{
    public const PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public static function isCanonical(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A'.self::PATTERN.'\z/', $value) === 1;
    }
}
