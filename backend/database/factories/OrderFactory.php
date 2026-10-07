<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Ordering\Enums\BrazilianState;
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
            // Free shipping keeps the default total (0) valid; tests that care set both.
            'shipping_cents' => 0,
            'delivery_business_days' => 2,
            'estimated_delivery_on' => null,
            'delivery_recipient_name' => fake()->name(),
            'delivery_postal_code' => fake()->numerify('########'),
            'delivery_street' => fake()->streetName(),
            'delivery_number' => (string) fake()->numberBetween(1, 9999),
            'delivery_complement' => null,
            'delivery_district' => fake()->words(2, true),
            'delivery_city' => fake()->city(),
            'delivery_state' => BrazilianState::SP,
            'status' => OrderStatus::Placed,
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
