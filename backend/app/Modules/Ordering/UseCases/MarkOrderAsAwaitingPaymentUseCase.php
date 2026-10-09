<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Repositories\OrderRepository;
use Illuminate\Support\Facades\Log;

/**
 * System: a freshly placed order starts waiting for its payment.
 */
final class MarkOrderAsAwaitingPaymentUseCase
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function execute(int $orderId): void
    {
        $order = $this->orders->findOrFail($orderId);

        if ($this->orders->transitionStatus($order, OrderStatus::Placed, OrderStatus::AwaitingPayment)) {
            Log::info('Order is awaiting payment.', ['order_id' => $order->id]);
        }
    }
}
