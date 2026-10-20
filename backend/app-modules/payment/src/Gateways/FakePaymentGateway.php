<?php

declare(strict_types=1);

namespace Modules\Payment\Gateways;

use Illuminate\Support\Str;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Core\DTOs\ChargeRequest;
use Modules\Payment\Core\DTOs\ChargeResult;
use Modules\Payment\Core\Enums\DeclineReason;
use Modules\Payment\Core\Enums\PaymentStatus;

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
