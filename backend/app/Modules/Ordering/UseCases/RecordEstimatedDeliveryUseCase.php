<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Ordering\Repositories\OrderRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * System (reacting to DeliveryScheduled): records the estimated delivery day on the order.
 * Only the first date is kept, and the order status is never touched here.
 */
final class RecordEstimatedDeliveryUseCase
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function execute(int $orderId, CarbonImmutable $estimatedOn): void
    {
        $order = $this->orders->findOrFail($orderId);

        if ($this->orders->recordEstimatedDelivery($order, $estimatedOn)) {
            Log::info('Order estimated delivery recorded.', [
                'order_id' => $order->id,
                'estimated_delivery_on' => $estimatedOn->format('Y-m-d'),
            ]);
        }
    }
}
