<?php

declare(strict_types=1);

namespace App\Modules\Shared\Enums;

use Symfony\Component\HttpFoundation\Response;

/**
 * Stable, machine readable error identifiers sent in the "code" field of every API error.
 * The frontend should branch on these values, never on the (translatable) message.
 */
enum ApiErrorCode: string
{
    case ValidationFailed = 'VALIDATION_FAILED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case CsrfTokenMismatch = 'CSRF_TOKEN_MISMATCH';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case BusinessRuleViolation = 'BUSINESS_RULE_VIOLATION';
    case InsufficientStock = 'INSUFFICIENT_STOCK';
    case HttpError = 'HTTP_ERROR';
    case ServerError = 'SERVER_ERROR';

    public static function fromHttpStatus(int $status): self
    {
        return match (true) {
            $status === Response::HTTP_UNAUTHORIZED => self::Unauthenticated,
            $status === Response::HTTP_FORBIDDEN => self::Forbidden,
            $status === Response::HTTP_NOT_FOUND => self::NotFound,
            $status === Response::HTTP_METHOD_NOT_ALLOWED => self::MethodNotAllowed,
            $status === 419 => self::CsrfTokenMismatch,
            $status === Response::HTTP_TOO_MANY_REQUESTS => self::TooManyRequests,
            $status >= Response::HTTP_INTERNAL_SERVER_ERROR => self::ServerError,
            default => self::HttpError,
        };
    }
}
