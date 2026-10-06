<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Exceptions;

use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown by the checkout when at least one product cannot be bought
 * in the requested quantity (inactive, out of stock or not enough units).
 */
class InsufficientStockException extends BusinessRuleException
{
    /**
     * @param  array<string, list<string>>  $errors  keyed by "items.{product_id}"
     */
    public function __construct(array $errors)
    {
        parent::__construct(
            'Estoque insuficiente para um ou mais produtos.',
            $errors,
            Response::HTTP_CONFLICT,
            ApiErrorCode::InsufficientStock,
        );
    }
}
