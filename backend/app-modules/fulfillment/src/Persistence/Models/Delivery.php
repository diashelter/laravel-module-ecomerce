<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Fulfillment\Database\Factories\DeliveryFactory;

/**
 * The fulfillment record of one paid order: the first estimated day and, once the carrier
 * delivered, when. One row per order (unique order_id), so it is what decides whether the
 * delivery was already scheduled or announced.
 */
#[Fillable(['order_id', 'estimated_delivery_on', 'delivered_at'])]
#[UseFactory(DeliveryFactory::class)]
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'estimated_delivery_on' => 'date',
            'delivered_at' => 'datetime',
        ];
    }
}
