<?php

declare(strict_types=1);

namespace App\Modules\Payment\Exceptions;

use App\Modules\Payment\Enums\DeclineReason;
use App\Modules\Shared\Enums\ApiErrorCode;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gateway declined the charge. Rendered as 402: the request was valid, the payment was not.
 * Thrown only after the declined attempt is committed, so the record survives the error.
 */
class PaymentDeclinedException extends BusinessRuleException
{
    public function __construct(public readonly DeclineReason $reason)
    {
        parent::__construct(
            $reason->message(),
            status: Response::HTTP_PAYMENT_REQUIRED,
            errorCode: ApiErrorCode::PaymentDeclined,
        );
    }
}
