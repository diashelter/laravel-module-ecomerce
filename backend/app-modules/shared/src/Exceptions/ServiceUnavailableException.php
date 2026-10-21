<?php

declare(strict_types=1);

namespace Modules\Shared\Exceptions;

use Illuminate\Http\JsonResponse;
use Modules\Shared\Enums\ApiErrorCode;
use Modules\Shared\Http\Responses\ApiErrorResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a module asked another one for is not installed: the port of the asking module has no module
 * behind it (see .design/module-isolation.md). Thrown before anything is written; rendered as 503.
 */
class ServiceUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Este recurso está indisponível no momento.');
    }

    public function render(): JsonResponse
    {
        return ApiErrorResponse::make(ApiErrorCode::ServiceUnavailable, $this->getMessage(), Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
