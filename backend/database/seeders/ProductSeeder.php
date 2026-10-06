<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public const PRODUCTS = 30;

    public const INACTIVE_PRODUCTS = 5;

    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $categoryIds = Category::query()->pluck('id');

        Product::factory()
            ->count(self::PRODUCTS)
            ->state(new Sequence(fn (Sequence $sequence) => [
                // Stable image seed based on the product position: product-1, product-2...
                'image_url' => sprintf('https://picsum.photos/seed/product-%d/600/600', $sequence->index + 1),
                // Every 6th product is inactive (5 of 30).
                'status' => ($sequence->index + 1) % 6 === 0 ? ProductStatus::Inactive : ProductStatus::Active,
            ]))
            ->create()
            ->each(function (Product $product) use ($categoryIds): void {
                $product->categories()->attach(
                    fake()->randomElements($categoryIds->all(), fake()->numberBetween(1, 3)),
                );
            });
    }
}
