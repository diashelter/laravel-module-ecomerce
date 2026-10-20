<?php

declare(strict_types=1);

namespace Modules\Payment\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Payment\Core\DTOs\ChargeRequest;
use Modules\Payment\Core\DTOs\ChargeResult;
use Modules\Payment\Core\Enums\PaymentStatus;
use Modules\Payment\Persistence\Models\Payment;
use Modules\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<Payment>
 */
class PaymentRepository extends BaseRepository
{
    protected function model(): string
    {
        return Payment::class;
    }

    public function hasApprovedForOrder(string $orderId): bool
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
