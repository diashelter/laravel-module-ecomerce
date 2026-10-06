<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every API error through ApiErrorResponse, so they all share the same shape.
 */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*', 'sanctum/*')) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return ApiErrorResponse::fromValidationException($e);
        }

        if ($e instanceof AuthenticationException) {
            return ApiErrorResponse::make(ApiErrorCode::Unauthenticated, 'Não autenticado.', Response::HTTP_UNAUTHORIZED);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            $message = match (true) {
                $status === Response::HTTP_FORBIDDEN => 'Você não tem permissão para realizar esta ação.',
                $e instanceof NotFoundHttpException => $this->notFoundMessage($e),
                $status === Response::HTTP_TOO_MANY_REQUESTS => 'Muitas tentativas. Aguarde um momento e tente novamente.',
                default => $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Erro.'),
            };

            return ApiErrorResponse::make(ApiErrorCode::fromHttpStatus($status), $message, $status, headers: $e->getHeaders());
        }

        // In debug mode, let Laravel show the full exception details.
        if (config('app.debug')) {
            return null;
        }

        return ApiErrorResponse::make(ApiErrorCode::ServerError, 'Erro interno do servidor.', Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function notFoundMessage(NotFoundHttpException $e): string
    {
        // Messages from abort(404, '...') are kept; route/model misses get a generic one.
        if ($e->getPrevious() !== null || $e->getMessage() === '' || str_starts_with($e->getMessage(), 'The route')) {
            return 'Recurso não encontrado.';
        }

        return $e->getMessage();
    }
}
