<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Persistence\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Modules\Fulfillment\Persistence\Models\Delivery;
use Modules\Shared\Repositories\BaseRepository;

/**
 * Every write is conditional and says whether it changed a row, so the caller announces an event
 * only for the write that did. The unique order_id arbitrates two deliveries of the same message
 * that run at once: the loser changes nothing and does not fail.
 *
 * @extends BaseRepository<Delivery>
 */
class DeliveryRepository extends BaseRepository
{
    protected function model(): string
    {
        return Delivery::class;
    }

    /** @return bool true when this call created the row, false when the order already had one */
    public function schedule(string $orderId, CarbonInterface $estimatedOn): bool
    {
        // insertOrIgnore skips the model, so the uuid and the timestamps are set here.
        return $this->query()->toBase()->insertOrIgnore([
            'id' => Str::uuid7()->toString(),
            'order_id' => $orderId,
            'estimated_delivery_on' => $estimatedOn->format('Y-m-d'),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    public function isDelivered(string $orderId): bool
    {
        return $this->query()->where('order_id', $orderId)->whereNotNull('delivered_at')->exists();
    }

    /** @return bool true when this call filled delivered_at, false when it was already filled or the order has no row */
    public function markDelivered(string $orderId, CarbonInterface $deliveredAt): bool
    {
        return $this->query()
            ->where('order_id', $orderId)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => $deliveredAt, 'updated_at' => $deliveredAt]) === 1;
    }
}
