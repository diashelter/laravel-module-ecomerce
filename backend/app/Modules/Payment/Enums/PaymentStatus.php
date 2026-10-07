<?php

declare(strict_types=1);

namespace App\Modules\Payment\Enums;

/**
 * Outcome of one payment attempt. Both values are final: an attempt is never updated.
 */
enum PaymentStatus: string
{
    case Approved = 'approved';
    case Declined = 'declined';
}
