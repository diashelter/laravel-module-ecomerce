<?php

declare(strict_types=1);

namespace Modules\Payment\Core\DTOs;

use Modules\Payment\Core\Enums\DeclineReason;
use Modules\Payment\Core\Enums\PaymentStatus;

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
