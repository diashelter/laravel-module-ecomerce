<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\UseCases;

use Illuminate\Support\Facades\Log;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Persistence\Repositories\OrderRepository;

/**
 * System (reacting to OrderDelivered): closes the order lifecycle.
 */
final class MarkOrderAsDeliveredUseCase
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function execute(string $orderId): void
    {
        $order = $this->orders->findOrFail($orderId);

        if ($this->orders->transitionStatus($order, OrderStatus::PaymentApproved, OrderStatus::Delivered)) {
            Log::info('Order delivered.', ['order_id' => $order->id]);
        }
    }
}
