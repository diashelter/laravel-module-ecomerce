<?php

declare(strict_types=1);

namespace Modules\Payment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Payment\Core\Enums\DeclineReason;
use Modules\Payment\Core\Enums\PaymentStatus;
use Modules\Payment\Persistence\Models\Payment;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            // The order belongs to the ordering module: a payment of a module test points at an id, never at a row.
            'order_id' => Str::uuid7()->toString(),
            'amount_cents' => 0,
            'status' => PaymentStatus::Approved,
            'decline_reason' => null,
            'card_token' => 'fake_card_approved',
            'gateway' => 'fake',
            'gateway_transaction_id' => 'fake_'.Str::uuid()->toString(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Approved, 'decline_reason' => null]);
    }

    public function declined(DeclineReason $reason = DeclineReason::CardDeclined): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Declined,
            'decline_reason' => $reason,
            'card_token' => 'fake_card_declined',
        ]);
    }
}
