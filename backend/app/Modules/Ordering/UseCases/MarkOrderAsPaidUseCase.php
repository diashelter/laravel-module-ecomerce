<?php

declare(strict_types=1);

namespace App\Modules\Ordering\UseCases;

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Events\OrderPaid;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Shared\Messaging\EventOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * System (reacting to PaymentApproved): marks the order as paid and announces it.
 * What happens next (delivery) is up to whoever listens to OrderPaid.
 */
final class MarkOrderAsPaidUseCase
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EventOutbox $outbox,
    ) {}

    public function execute(string $orderId): void
    {
        $order = $this->orders->findOrFail($orderId);

        // The transition and OrderPaid commit together: if recording fails, the order stays awaiting
        // payment and the retried delivery moves it. A duplicated or late PaymentApproved moves
        // nothing and records nothing, so OrderPaid is recorded only once.
        $paid = DB::transaction(function () use ($order): bool {
            if (! $this->orders->transitionStatus($order, OrderStatus::AwaitingPayment, OrderStatus::PaymentApproved)) {
                return false;
            }

            $this->outbox->record(new OrderPaid($order->id, $order->delivery_business_days));

            return true;
        });

        if ($paid) {
            Log::info('Order payment approved.', ['order_id' => $order->id]);
        }
    }
}
