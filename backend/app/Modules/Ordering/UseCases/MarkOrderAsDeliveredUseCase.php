<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Repositories\OrderRepository;
use Illuminate\Support\Facades\Log;

/**
 * System (reacting to OrderDelivered): closes the order lifecycle.
 */
final class MarkOrderAsDeliveredUseCase
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function execute(int $orderId): void
    {
        $order = $this->orders->findOrFail($orderId);

        if ($this->orders->transitionStatus($order, OrderStatus::PaymentApproved, OrderStatus::Delivered)) {
            Log::info('Order delivered.', ['order_id' => $order->id]);
        }
    }
}
