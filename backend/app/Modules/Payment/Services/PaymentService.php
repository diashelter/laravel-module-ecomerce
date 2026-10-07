<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Shared\Exceptions\BusinessRuleException;

/**
 * The rule of which orders can be paid. The approved payment is checked too because the queue
 * moves the order to payment_approved only a moment after the approval is recorded.
 */
class PaymentService
{
    /**
     * @throws BusinessRuleException
     */
    public function ensureCanBePaid(Order $order, bool $hasApprovedPayment): void
    {
        if ($order->status !== OrderStatus::AwaitingPayment || $hasApprovedPayment) {
            throw $this->notPayable();
        }
    }

    /** The error for an order that can no longer be paid (409). */
    public function notPayable(): BusinessRuleException
    {
        return new BusinessRuleException('Este pedido não está aguardando pagamento.');
    }
}
