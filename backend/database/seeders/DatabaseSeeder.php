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
        // Faker reseeds PHP's global random generator whenever one of its generators is destroyed,
        // and the garbage collector may destroy leftovers (from an earlier app) at any moment.
        // Collecting them now keeps one from reseeding in the middle of this run.
        gc_collect_cycles();

        fake()->seed(self::FAKER_SEED);
        fake()->unique(reset: true);

        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            StockSeeder::class,
            CustomerAddressSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
