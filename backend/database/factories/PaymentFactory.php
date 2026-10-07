<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Enums\DeclineReason;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
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
