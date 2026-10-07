<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Models;

use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Policies\OrderPolicy;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id', 'total_cents', 'status',
    'shipping_cents', 'delivery_business_days', 'estimated_delivery_on',
    'delivery_recipient_name', 'delivery_postal_code', 'delivery_street', 'delivery_number',
    'delivery_complement', 'delivery_district', 'delivery_city', 'delivery_state',
])]
#[UseFactory(OrderFactory::class)]
#[UsePolicy(OrderPolicy::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'shipping_cents' => 'integer',
            'delivery_business_days' => 'integer',
            'estimated_delivery_on' => 'date',
            'delivery_state' => BrazilianState::class,
            'status' => OrderStatus::class,
        ];
    }

    /**
     * Who placed the order, identified by the customer account id.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
