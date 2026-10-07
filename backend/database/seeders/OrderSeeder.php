<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use Illuminate\Database\Seeder;

/**
 * Historical demo orders so the dashboard has data right after installation.
 *
 * These orders do not decrement stock: they represent sales that happened in the past,
 * before the current stock count was taken.
 */
class OrderSeeder extends Seeder
{
    public const ORDERS = 24;

    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $customers = CustomerAccount::query()->get();
        $products = Product::query()->get();
        $statuses = OrderStatus::cases();

        for ($i = 0; $i < self::ORDERS; $i++) {
            // Half of the orders in the last 30 days (cycling through every status),
            // the other half spread over the last 12 months (already delivered).
            $isRecent = $i % 2 === 0;
            $createdAt = $isRecent
                ? now()->subDays(fake()->numberBetween(0, 29))
                : now()->subMonths(fake()->numberBetween(1, 11))->subDays(fake()->numberBetween(0, 27));
            $createdAt = $createdAt->setTime(fake()->numberBetween(8, 22), fake()->numberBetween(0, 59));
            $status = $isRecent ? $statuses[intdiv($i, 2) % count($statuses)] : OrderStatus::Delivered;

            $order = Order::factory()
                ->for(fake()->randomElement($customers->all()), 'customer')
                ->status($status)
                ->create(['created_at' => $createdAt, 'updated_at' => $createdAt]);

            $totalCents = 0;

            foreach (fake()->randomElements($products->all(), fake()->numberBetween(1, 4)) as $product) {
                $item = OrderItem::factory()
                    ->for($order)
                    ->forProduct($product, fake()->numberBetween(1, 3))
                    ->create(['created_at' => $createdAt, 'updated_at' => $createdAt]);

                $totalCents += $item->subtotal_cents;
            }

            $order->total_cents = $totalCents;
            $order->timestamps = false;
            $order->save();
        }
    }
}
