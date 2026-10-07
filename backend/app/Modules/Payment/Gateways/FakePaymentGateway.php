<?php

declare(strict_types=1);

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResult;
use App\Modules\Payment\Enums\DeclineReason;
use App\Modules\Payment\Enums\PaymentStatus;
use Illuminate\Support\Str;

/**
 * Sandbox gateway: the outcome depends only on the test card token, never on the amount,
 * as in the test cards real gateways document. Any unknown token is an invalid card.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public function charge(ChargeRequest $request): ChargeResult
    {
        $declineReason = match ($request->cardToken) {
            'fake_card_approved' => null,
            'fake_card_insufficient_funds' => DeclineReason::InsufficientFunds,
            'fake_card_declined' => DeclineReason::CardDeclined,
            default => DeclineReason::InvalidCard,
        };

        return new ChargeResult(
            status: $declineReason === null ? PaymentStatus::Approved : PaymentStatus::Declined,
            declineReason: $declineReason,
            transactionId: 'fake_'.Str::uuid()->toString(),
            gateway: 'fake',
        );
    }
}
