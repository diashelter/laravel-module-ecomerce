<?php

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Fake payment: there is no gateway, only the rule of which orders can be paid.
 */
class PaymentService
{
    /**
     * @throws BusinessRuleException
     */
    public function ensureCanBePaid(Order $order): void
    {
        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw new BusinessRuleException('Este pedido não está aguardando pagamento.');
        }
    }
}
