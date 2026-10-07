<?php

declare(strict_types=1);

namespace App\Modules\Payment\DTOs;

use App\Modules\Payment\Enums\DeclineReason;
use App\Modules\Payment\Enums\PaymentStatus;

/**
 * Answer of a gateway to a charge. The decline reason is set only when the charge was declined.
 */
final readonly class ChargeResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?DeclineReason $declineReason,
        public string $transactionId,
        public string $gateway,
    ) {}
}
