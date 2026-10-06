<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Fixed seed: running `migrate:fresh --seed` always produces the same demo data.
     */
    public const FAKER_SEED = 2026;

    public function run(): void
    {
        fake()->seed(self::FAKER_SEED);
        fake()->unique(reset: true);

        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            StockSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
