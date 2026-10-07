<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Fulfillment\Services\DeliveryCalendar;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Ordering\Contracts\ShippingQuoter;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use Illuminate\Database\Seeder;

/**
 * Historical demo orders so the dashboard has data right after installation.
 *
 * These orders do not decrement stock: they represent sales that happened in the past,
 * before the current stock count was taken. Each one is delivered to an address of its customer
 * (a copy, like a real order) with the shipping of the rate table for that state.
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
        $addressesByCustomer = CustomerAddress::query()->orderBy('id')->get()->groupBy('customer_id');
        $products = Product::query()->get();
        $statuses = OrderStatus::cases();
        $quoter = app(ShippingQuoter::class);
        $calendar = app(DeliveryCalendar::class);

        for ($i = 0; $i < self::ORDERS; $i++) {
            // Half of the orders in the last 30 days (cycling through every status),
            // the other half spread over the last 12 months (already delivered).
            $isRecent = $i % 2 === 0;
            $createdAt = $isRecent
                ? now()->subDays(fake()->numberBetween(0, 29))
                : now()->subMonths(fake()->numberBetween(1, 11))->subDays(fake()->numberBetween(0, 27));
            $createdAt = $createdAt->setTime(fake()->numberBetween(8, 22), fake()->numberBetween(0, 59));
            $status = $isRecent ? $statuses[intdiv($i, 2) % count($statuses)] : OrderStatus::Delivered;

            $customer = fake()->randomElement($customers->all());
            $address = fake()->randomElement($addressesByCustomer[$customer->id]->all());
            $shipping = $quoter->quote($address->state);

            // The estimate exists only once the payment was approved: it counts from the payment day.
            $isPaid = in_array($status, [OrderStatus::PaymentApproved, OrderStatus::Delivered], true);

            $order = Order::factory()
                ->for($customer, 'customer')
                ->status($status)
                ->create([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    // The items are added below; until then the total is just the shipping.
                    'total_cents' => $shipping->priceCents,
                    'shipping_cents' => $shipping->priceCents,
                    'delivery_business_days' => $shipping->deliveryBusinessDays,
                    'estimated_delivery_on' => $isPaid ? $calendar->estimate($createdAt, $shipping->deliveryBusinessDays)->toDateString() : null,
                    'delivery_recipient_name' => $address->recipient_name,
                    'delivery_postal_code' => $address->postal_code,
                    'delivery_street' => $address->street,
                    'delivery_number' => $address->number,
                    'delivery_complement' => $address->complement,
                    'delivery_district' => $address->district,
                    'delivery_city' => $address->city,
                    'delivery_state' => $address->state,
                ]);

            $totalCents = 0;

            foreach (fake()->randomElements($products->all(), fake()->numberBetween(1, 4)) as $product) {
                $item = OrderItem::factory()
                    ->for($order)
                    ->forProduct($product, fake()->numberBetween(1, 3))
                    ->create(['created_at' => $createdAt, 'updated_at' => $createdAt]);

                $totalCents += $item->subtotal_cents;
            }

            $order->total_cents = $totalCents + $shipping->priceCents;
            $order->timestamps = false;
            $order->save();
        }
    }
}
