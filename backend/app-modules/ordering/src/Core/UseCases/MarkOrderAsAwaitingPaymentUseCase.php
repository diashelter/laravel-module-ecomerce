<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\UseCases;

use Illuminate\Support\Facades\Log;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Persistence\Repositories\OrderRepository;

/**
 * System: a freshly placed order starts waiting for its payment.
 */
final class MarkOrderAsAwaitingPaymentUseCase
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function execute(string $orderId): void
    {
        $order = $this->orders->findOrFail($orderId);

        if ($this->orders->transitionStatus($order, OrderStatus::Placed, OrderStatus::AwaitingPayment)) {
            Log::info('Order is awaiting payment.', ['order_id' => $order->id]);
        }
    }
}
