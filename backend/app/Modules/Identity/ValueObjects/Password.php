<?php

declare(strict_types=1);

namespace App\Modules\Identity\ValueObjects;

use InvalidArgumentException;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * A password somebody chose, which already complies with the policy (minimum length).
 * The login does not use it: old passwords must keep working after the policy changes.
 *
 * The plain text never shows up in dumps, serialization or stack traces: it is wrapped in a
 * `SensitiveParameterValue` and the constructor argument is marked as sensitive. There is no
 * `__toString`; the text leaves only through `reveal()`, right before it is hashed.
 */
final readonly class Password
{
    public const MIN_LENGTH = 8;

    private SensitiveParameterValue $plain;

    public function __construct(#[SensitiveParameter] string $plain)
    {
        if (! self::isLongEnough($plain)) {
            throw new InvalidArgumentException('The password must have at least '.self::MIN_LENGTH.' characters.');
        }

        $this->plain = new SensitiveParameterValue($plain);
    }

    public static function isLongEnough(#[SensitiveParameter] string $plain): bool
    {
        return mb_strlen($plain) >= self::MIN_LENGTH;
    }

    public function reveal(): string
    {
        return $this->plain->getValue();
    }
}
