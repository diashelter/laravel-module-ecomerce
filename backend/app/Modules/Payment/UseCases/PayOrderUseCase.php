<?php

declare(strict_types=1);

namespace App\Modules\Payment\UseCases;

use App\Modules\Ordering\Contracts\PayableOrders;
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
use App\Modules\Shared\Messaging\EventOutbox;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Customer: pays an order through the payment gateway. Every attempt is recorded.
 * An approval is only announced (PaymentApproved, recorded with the approved attempt): the order
 * status is changed asynchronously by the ordering side (see MarkOrderAsPaidUseCase). A decline
 * leaves the order as it was.
 */
final class PayOrderUseCase
{
    public function __construct(
        private readonly PayableOrders $orders,
        private readonly PaymentService $rules,
        private readonly PaymentRepository $payments,
        private readonly PaymentGateway $gateway,
        private readonly EventOutbox $outbox,
    ) {}

    /**
     * @throws ModelNotFoundException when the order is not among the customer's own (404, checked before the status)
     * @throws BusinessRuleException when the order cannot be paid (409)
     * @throws PaymentDeclinedException when the gateway declines the charge (402)
     */
    public function execute(string $customerId, string $orderId, PayOrderDTO $data): Payment
    {
        $order = $this->orders->findForPayment($customerId, $orderId)
            ?? throw (new ModelNotFoundException)->setModel(OrderForPayment::class, [$orderId]);

        $this->rules->ensureCanBePaid($order, $this->payments->hasApprovedForOrder($order->id));

        // The amount always comes from the order, never from the request.
        $request = new ChargeRequest($order->id, $order->totalCents, $data->cardToken);
        $result = $this->gateway->charge($request);

        try {
            // A savepoint when a transaction is already open: the unique violation then rolls back
            // only this insert instead of aborting the caller's transaction (PostgreSQL). The approved
            // attempt and its PaymentApproved commit together: neither exists without the other.
            $payment = DB::transaction(function () use ($request, $result, $order): Payment {
                $payment = $this->payments->record($request, $result);

                if ($result->status === PaymentStatus::Approved) {
                    $this->outbox->record(new PaymentApproved($order->id));
                }

                return $payment;
            });
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

        return $payment;
    }
}
