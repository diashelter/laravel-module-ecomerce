<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Fulfillment\Persistence\Models\Delivery;
use Modules\Ordering\Persistence\Models\Order;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'estimated_delivery_on' => now()->toDateString(),
            'delivered_at' => null,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['delivered_at' => now()]);
    }
}
