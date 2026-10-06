<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Http\Responses\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A business rule was violated (e.g. not enough stock). Rendered as a standard JSON error.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        protected array $errors = [],
        protected int $status = Response::HTTP_CONFLICT,
        protected ApiErrorCode $errorCode = ApiErrorCode::BusinessRuleViolation,
    ) {
        parent::__construct($message);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function render(): JsonResponse
    {
        return ApiErrorResponse::make($this->errorCode, $this->getMessage(), $this->status, $this->errors);
    }
}
