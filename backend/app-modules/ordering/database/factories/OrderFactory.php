<?php

declare(strict_types=1);

namespace Modules\Ordering\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Ordering\Core\Enums\BrazilianState;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Core\Services\OrderCodeGenerator;
use Modules\Ordering\Persistence\Models\Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            // Drawn directly, not through the container: a test may replace the generator there.
            'code' => (new OrderCodeGenerator)->generate(),
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
