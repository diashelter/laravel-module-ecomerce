<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => CustomerAccount::factory(),
            'total_cents' => 0,
            'status' => OrderStatus::Placed,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
