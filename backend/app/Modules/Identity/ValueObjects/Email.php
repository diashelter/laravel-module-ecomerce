<?php

declare(strict_types=1);

namespace App\Modules\Identity\ValueObjects;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use InvalidArgumentException;

/**
 * A valid, canonical account e-mail: no surrounding spaces and always lower case, so that
 * addresses differing only in case are the same account. `users.email` has a CHECK that
 * enforces the same canonical form (see the users migration).
 *
 * There is deliberately no `__toString`: the text leaves only through `value()`.
 */
final readonly class Email
{
    public const MAX_LENGTH = 255;

    private string $value;

    public function __construct(string $email)
    {
        $normalized = self::normalize($email);

        if (self::isTooLong($normalized)) {
            throw new InvalidArgumentException('The e-mail must have at most '.self::MAX_LENGTH.' characters.');
        }

        if (! self::isWellFormed($normalized)) {
            throw new InvalidArgumentException('The e-mail is not a valid address.');
        }

        $this->value = $normalized;
    }

    /**
     * Single place that decides the canonical form. Form Requests use it to normalize the
     * input before the `unique` rule compares it with what is stored.
     */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function isTooLong(string $normalized): bool
    {
        return mb_strlen($normalized) > self::MAX_LENGTH;
    }

    public static function isWellFormed(string $normalized): bool
    {
        return (new EmailValidator)->isValid($normalized, new RFCValidation);
    }

    public function value(): string
    {
        return $this->value;
    }
}
