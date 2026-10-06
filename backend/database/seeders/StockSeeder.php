<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Seeder;

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
        $products = Product::query()->doesntHave('stock')->orderBy('id')->get()->shuffle(DatabaseSeeder::FAKER_SEED);

        $ranges = [];
        foreach (self::DISTRIBUTION as [$count, $min, $max]) {
            array_push($ranges, ...array_fill(0, $count, [$min, $max]));
        }

        foreach ($products->values() as $index => $product) {
            [$min, $max] = $ranges[$index] ?? [21, 150];

            $product->stock()->create(['quantity' => fake()->numberBetween($min, $max)]);
        }
    }
}
