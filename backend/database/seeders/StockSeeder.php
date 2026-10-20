<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Inventory\Persistence\Models\Stock;

class StockSeeder extends Seeder
{
    /**
     * Stock distribution: [how many products, min quantity, max quantity].
     * Whatever is left after these groups receives a high stock (21-150).
     */
    private const DISTRIBUTION = [
        [6, 0, 0],   // out of stock
        [6, 1, 5],   // low stock
        [9, 6, 20],  // medium stock
    ];

    public function run(): void
    {
        $products = Product::query()->whereNotIn('id', Stock::query()->select('product_id'))->orderBy('id')->get()->shuffle(DatabaseSeeder::FAKER_SEED);

        $ranges = [];
        foreach (self::DISTRIBUTION as [$count, $min, $max]) {
            array_push($ranges, ...array_fill(0, $count, [$min, $max]));
        }

        foreach ($products->values() as $index => $product) {
            [$min, $max] = $ranges[$index] ?? [21, 150];

            Stock::query()->create(['product_id' => $product->id, 'quantity' => fake()->numberBetween($min, $max)]);
        }
    }
}
