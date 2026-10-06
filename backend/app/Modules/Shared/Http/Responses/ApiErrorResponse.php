<?php

declare(strict_types=1);

namespace App\Modules\Shared\Http\Responses;

use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Http\Middleware\AssignRequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for the shape of every API error sent to the frontend:
 * {"code": "...", "message": "...", "errors": {"field": ["..."]}, "request_id": "..."}.
 */
final class ApiErrorResponse
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string>  $headers
     */
    public static function make(
        ApiErrorCode $code,
        string $message,
        int $status,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'code' => $code->value,
            'message' => $message,
            'errors' => (object) $errors,
            'request_id' => AssignRequestId::current(),
        ], $status, [
            ...$headers,
            // Errors are user specific and must never be stored by browsers or proxies.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function fromValidationException(ValidationException $e): JsonResponse
    {
        return self::make(ApiErrorCode::ValidationFailed, $e->getMessage(), $e->status, $e->errors());
    }
}
