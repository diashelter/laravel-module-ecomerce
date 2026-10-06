<?php

declare(strict_types=1);

namespace App\Modules\Payment\UseCases;

use App\Modules\Ordering\Models\Order;
use App\Modules\Payment\Events\PaymentApproved;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Customer: pays an order (fake payment, approved immediately).
 * Payment only announces the approval: the order status is changed asynchronously by the
 * ordering side (see MarkOrderAsPaidUseCase).
 */
final class PayOrderUseCase
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * @throws BusinessRuleException
     */
    public function execute(Order $order): Order
    {
        $this->payments->ensureCanBePaid($order);

        PaymentApproved::dispatch($order);

        return $order->load('items');
    }
}
