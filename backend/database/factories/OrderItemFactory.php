<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'unit_price_cents' => 1000,
            'quantity' => 1,
            'subtotal_cents' => 1000,
        ];
    }

    /**
     * Copies the product's current name and price as a historical snapshot.
     */
    public function forProduct(Product $product, int $quantity): static
    {
        return $this->state(fn () => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price_cents' => $product->price_cents,
            'quantity' => $quantity,
            'subtotal_cents' => $product->price_cents * $quantity,
        ]);
    }
}
