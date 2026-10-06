<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(0, 100),
        ];
    }
}
