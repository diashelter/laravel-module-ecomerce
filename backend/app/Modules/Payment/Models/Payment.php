<?php

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use App\Modules\Payment\Enums\DeclineReason;
use App\Modules\Payment\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One attempt to charge an order. Approved and declined are final: the row is never updated.
 */
#[Fillable(['order_id', 'amount_cents', 'status', 'decline_reason', 'card_token', 'gateway', 'gateway_transaction_id'])]
#[UseFactory(PaymentFactory::class)]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'status' => PaymentStatus::class,
            'decline_reason' => DeclineReason::class,
        ];
    }
}
