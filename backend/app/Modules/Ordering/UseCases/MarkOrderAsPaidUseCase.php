<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderPaid;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Repositories\OrderRepository;
use Illuminate\Support\Facades\Log;

/**
 * System (reacting to PaymentApproved): marks the order as paid and announces it.
 * What happens next (delivery) is up to whoever listens to OrderPaid.
 */
final class MarkOrderAsPaidUseCase
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function execute(Order $order): void
    {
        // A duplicated or late PaymentApproved does nothing, so OrderPaid is fired only once.
        if (! $this->orders->transitionStatus($order, OrderStatus::AwaitingPayment, OrderStatus::PaymentApproved)) {
            return;
        }

        Log::info('Order payment approved.', ['order_id' => $order->id]);

        OrderPaid::dispatch($order);
    }
}
