<?php

declare(strict_types=1);

namespace App\Modules\Payment\UseCases;

use App\Modules\Ordering\ValueObjects\OrderForPayment;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\PayOrderDTO;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Payment\Exceptions\PaymentDeclinedException;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Repositories\PaymentRepository;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Customer: pays an order through the payment gateway. Every attempt is recorded.
 * An approval is only announced (PaymentApproved): the order status is changed asynchronously
 * by the ordering side (see MarkOrderAsPaidUseCase). A decline leaves the order as it was.
 */
final class PayOrderUseCase
{
    public function __construct(
        private readonly PaymentService $rules,
        private readonly PaymentRepository $payments,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @throws BusinessRuleException when the order cannot be paid (409)
     * @throws PaymentDeclinedException when the gateway declines the charge (402)
     */
    public function execute(OrderForPayment $order, PayOrderDTO $data): Payment
    {
        $this->rules->ensureCanBePaid($order, $this->payments->hasApprovedForOrder($order->id));

        // The amount always comes from the order, never from the request.
        $request = new ChargeRequest($order->id, $order->totalCents, $data->cardToken);
        $result = $this->gateway->charge($request);

        try {
            // A savepoint when a transaction is already open: the unique violation then rolls back
            // only this insert instead of aborting the caller's transaction (PostgreSQL).
            $payment = DB::transaction(fn (): Payment => $this->payments->record($request, $result));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request recorded the approval first: this order is no longer payable.
            throw $this->rules->notPayable();
        }

        Log::info('Payment attempt recorded.', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'status' => $payment->status->value,
            'decline_reason' => $payment->decline_reason?->value,
        ]);

        // Thrown only now, after the declined attempt was recorded, so the record survives the 402.
        if ($result->status === PaymentStatus::Declined) {
            throw new PaymentDeclinedException($result->declineReason);
        }

        PaymentApproved::dispatch($order->id);

        return $payment;
    }
}
