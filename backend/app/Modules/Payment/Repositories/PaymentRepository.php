<?php

declare(strict_types=1);

namespace App\Modules\Payment\Repositories;

use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResult;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Models\Payment;
use App\Modules\Shared\Repositories\BaseRepository;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * @extends BaseRepository<Payment>
 */
class PaymentRepository extends BaseRepository
{
    protected function model(): string
    {
        return Payment::class;
    }

    public function hasApprovedForOrder(int $orderId): bool
    {
        return $this->query()
            ->where('order_id', $orderId)
            ->where('status', PaymentStatus::Approved)
            ->exists();
    }

    /**
     * Stores the attempt as the gateway answered it.
     *
     * @throws UniqueConstraintViolationException when the order already has an approved payment
     */
    public function record(ChargeRequest $request, ChargeResult $result): Payment
    {
        return $this->create([
            'order_id' => $request->orderId,
            'amount_cents' => $request->amountCents,
            'status' => $result->status,
            'decline_reason' => $result->declineReason,
            'card_token' => $request->cardToken,
            'gateway' => $result->gateway,
            'gateway_transaction_id' => $result->transactionId,
        ]);
    }
}
