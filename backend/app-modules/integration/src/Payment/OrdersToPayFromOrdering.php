<?php

declare(strict_types=1);

namespace Modules\Integration\Payment;

use Modules\Ordering\Contracts\PayableOrders;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Payment\Contracts\OrdersToPay;
use Modules\Payment\Core\ValueObjects\OrderToPay;

/**
 * The order port of the payment module, served by the ordering module: translates the order the ordering
 * module offers into the one the payment module asks for. Only the ordering module knows its statuses.
 */
final class OrdersToPayFromOrdering implements OrdersToPay
{
    public function __construct(private readonly PayableOrders $orders) {}

    public function findForPayment(string $customerId, string $orderId): ?OrderToPay
    {
        $order = $this->orders->findForPayment($customerId, $orderId);

        return $order === null ? null : new OrderToPay(
            $order->id,
            $order->customerId,
            $order->totalCents,
            $order->status === OrderStatus::AwaitingPayment,
        );
    }
}
