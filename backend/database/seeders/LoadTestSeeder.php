<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Fulfillment\Services\DeliveryCalendar;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Ordering\Contracts\ShippingQuoter;
use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Services\OrderCodeGenerator;
use App\Modules\Payment\Enums\PaymentStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The dataset of the load test stack (load-tests/, HEL-14). The DatabaseSeeder never calls it: it runs only
 * on the load database, which is recreated before every load test run.
 *
 * The baseline compares runs, so every run must produce the same data: the Faker seed is fixed, and the ids
 * are drawn up front and sorted, so ordering by id follows the creation order even inside one millisecond.
 * Rows go in bulk to keep the whole seed under 90 seconds.
 */
class LoadTestSeeder extends Seeder
{
    public const CUSTOMERS = 1000;

    public const AVAILABLE_PRODUCTS = 170;

    public const AVAILABLE_UNITS = 1_000_000;

    public const SOLD_OUT_PRODUCTS = 10;

    public const INACTIVE_PRODUCTS = 20;

    public const INACTIVE_UNITS = 100;

    public const DISPUTED_PRODUCT = 'Produto Disputado';

    public const DISPUTED_UNITS = 10;

    public const ORDERS = 10_000;

    private const CHUNK = 1000;

    public function run(): void
    {
        // Same reason as the DatabaseSeeder: a leftover Faker generator must not reseed in the middle of the run.
        gc_collect_cycles();

        fake()->seed(DatabaseSeeder::FAKER_SEED);
        fake()->unique(reset: true);

        $this->call(CategorySeeder::class);

        $password = Hash::make(UserFactory::DEFAULT_PASSWORD);

        $this->seedStaff($password);
        $customers = $this->seedCustomers($password);
        $addresses = $this->seedAddresses($customers);
        $available = $this->seedProducts();
        $this->seedOrders($customers, $addresses, $available);
    }

    private function seedStaff(string $password): void
    {
        $now = now();
        $ids = $this->ids(2);

        DB::table('users')->insert([
            ['id' => $ids[0], 'name' => 'Admin', 'email' => 'admin@example.com', 'email_verified_at' => $now, 'password' => $password, 'role' => UserRole::Admin->value, 'created_at' => $now, 'updated_at' => $now],
            ['id' => $ids[1], 'name' => 'Suporte', 'email' => 'suporte@example.com', 'email_verified_at' => $now, 'password' => $password, 'role' => UserRole::Support->value, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * @return list<array{id: string, name: string}> indexed by the customer number (load-customer-<n>)
     */
    private function seedCustomers(string $password): array
    {
        $now = now();
        $customers = [];
        $rows = [];

        foreach ($this->ids(self::CUSTOMERS) as $number => $id) {
            $name = fake()->name();
            $customers[] = ['id' => $id, 'name' => $name];
            $rows[] = [
                'id' => $id,
                'name' => $name,
                'email' => "load-customer-{$number}@example.com",
                'password' => $password,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insert('customers', $rows);

        return $customers;
    }

    /**
     * One address per customer, in a state drawn from all of them.
     *
     * @param  list<array{id: string, name: string}>  $customers
     * @return list<array<string, string|null>> the address of each customer, in the customer order
     */
    private function seedAddresses(array $customers): array
    {
        $now = now();
        $states = BrazilianState::cases();
        $ids = $this->ids(count($customers));
        $addresses = [];

        foreach ($customers as $index => $customer) {
            $addresses[] = [
                'id' => $ids[$index],
                'customer_id' => $customer['id'],
                'recipient_name' => $customer['name'],
                'postal_code' => fake()->numerify('########'),
                'street' => fake()->streetName(),
                'number' => (string) fake()->numberBetween(1, 9999),
                'complement' => null,
                'district' => fake()->words(2, true),
                'city' => fake()->city(),
                'state' => fake()->randomElement($states)->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insert('customer_addresses', $addresses);

        return $addresses;
    }

    /**
     * 170 available products, 10 sold out, 20 inactive and the one the contention profile disputes.
     *
     * @return list<array{id: string, name: string, price_cents: int}> the available products
     */
    private function seedProducts(): array
    {
        $groups = [
            [self::AVAILABLE_PRODUCTS, ProductStatus::Active, self::AVAILABLE_UNITS],
            [self::SOLD_OUT_PRODUCTS, ProductStatus::Active, 0],
            [self::INACTIVE_PRODUCTS, ProductStatus::Inactive, self::INACTIVE_UNITS],
            [1, ProductStatus::Active, self::DISPUTED_UNITS],
        ];

        $total = array_sum(array_column($groups, 0));
        $productIds = $this->ids($total);
        $stockIds = $this->ids($total);
        $categoryIds = Category::query()->orderBy('name')->pluck('id')->all();
        $now = now();
        $products = [];
        $stocks = [];
        $categories = [];
        $available = [];
        $position = 0;

        foreach ($groups as $group => [$count, $status, $units]) {
            for ($i = 0; $i < $count; $i++, $position++) {
                $attributes = Product::factory()->raw();
                $name = $group === 3 ? self::DISPUTED_PRODUCT : $attributes['name'];
                $id = $productIds[$position];

                $products[] = [
                    'id' => $id,
                    'name' => $name,
                    'price_cents' => $attributes['price_cents'],
                    'description' => $attributes['description'],
                    'image_url' => $attributes['image_url'],
                    'status' => $status->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $stocks[] = ['id' => $stockIds[$position], 'product_id' => $id, 'quantity' => $units, 'created_at' => $now, 'updated_at' => $now];

                foreach (fake()->randomElements($categoryIds, fake()->numberBetween(1, 3)) as $categoryId) {
                    $categories[] = ['product_id' => $id, 'category_id' => $categoryId];
                }

                if ($group === 0) {
                    $available[] = ['id' => $id, 'name' => $name, 'price_cents' => $attributes['price_cents']];
                }
            }
        }

        $this->insert('products', $products);
        $this->insert('stocks', $stocks);
        $this->insert('category_product', $categories);

        return $available;
    }

    /**
     * Past orders spread like the OrderSeeder: half in the last 30 days cycling through every status, half
     * over the last 12 months already delivered. They do not decrement stock, and they write no outbox message:
     * they are history, not checkouts. The first one belongs to load-customer-0, whose orders no VU owns.
     *
     * @param  list<array{id: string, name: string}>  $customers
     * @param  list<array<string, string|null>>  $addresses
     * @param  list<array{id: string, name: string, price_cents: int}>  $available
     */
    private function seedOrders(array $customers, array $addresses, array $available): void
    {
        $quoter = app(ShippingQuoter::class);
        $calendar = app(DeliveryCalendar::class);
        $codes = new OrderCodeGenerator;
        $statuses = OrderStatus::cases();
        $orderIds = $this->ids(self::ORDERS);
        $usedCodes = [];
        $orders = [];
        $items = [];
        $payments = [];

        for ($i = 0; $i < self::ORDERS; $i++) {
            $isRecent = $i % 2 === 0;
            $createdAt = $isRecent
                ? now()->subDays(fake()->numberBetween(0, 29))
                : now()->subMonths(fake()->numberBetween(1, 11))->subDays(fake()->numberBetween(0, 27));
            $createdAt = $createdAt->setTime(fake()->numberBetween(8, 22), fake()->numberBetween(0, 59));
            $status = $isRecent ? $statuses[intdiv($i, 2) % count($statuses)] : OrderStatus::Delivered;
            $customerNumber = $i === 0 ? 0 : fake()->numberBetween(0, self::CUSTOMERS - 1);
            $address = $addresses[$customerNumber];
            $shipping = $quoter->quote(BrazilianState::from($address['state']));
            $isPaid = in_array($status, [OrderStatus::PaymentApproved, OrderStatus::Delivered], true);
            $orderId = $orderIds[$i];
            $itemsTotal = 0;

            foreach (fake()->randomElements($available, fake()->numberBetween(1, 4)) as $product) {
                $quantity = fake()->numberBetween(1, 3);
                $subtotal = $product['price_cents'] * $quantity;
                $itemsTotal += $subtotal;
                $items[] = [
                    'id' => (string) Str::uuid7(),
                    'order_id' => $orderId,
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'unit_price_cents' => $product['price_cents'],
                    'quantity' => $quantity,
                    'subtotal_cents' => $subtotal,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }

            $total = $itemsTotal + $shipping->priceCents;
            $orders[] = [
                'id' => $orderId,
                'code' => $this->uniqueCode($codes, $usedCodes),
                'customer_id' => $customers[$customerNumber]['id'],
                'total_cents' => $total,
                'shipping_cents' => $shipping->priceCents,
                'delivery_business_days' => $shipping->deliveryBusinessDays,
                'estimated_delivery_on' => $isPaid ? $calendar->estimate($createdAt, $shipping->deliveryBusinessDays)->toDateString() : null,
                'delivery_recipient_name' => $address['recipient_name'],
                'delivery_postal_code' => $address['postal_code'],
                'delivery_street' => $address['street'],
                'delivery_number' => $address['number'],
                'delivery_complement' => $address['complement'],
                'delivery_district' => $address['district'],
                'delivery_city' => $address['city'],
                'delivery_state' => $address['state'],
                'status' => $status->value,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if ($isPaid) {
                $payments[] = $this->approvedPayment($orderId, $total, $createdAt);
            }
        }

        $this->insert('orders', $orders);
        $this->insert('order_items', $items);
        $this->insert('payments', $payments);
    }

    /**
     * @return array<string, int|string|Carbon>
     */
    private function approvedPayment(string $orderId, int $amountCents, Carbon $paidAt): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'order_id' => $orderId,
            'amount_cents' => $amountCents,
            'status' => PaymentStatus::Approved->value,
            'decline_reason' => null,
            'card_token' => 'fake_card_approved',
            'gateway' => 'fake',
            'gateway_transaction_id' => 'fake_'.Str::uuid()->toString(),
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ];
    }

    /**
     * The order code is drawn at random, as at checkout; a repeat within the batch would break the unique index.
     *
     * @param  array<string, true>  $used
     */
    private function uniqueCode(OrderCodeGenerator $codes, array &$used): string
    {
        do {
            $code = $codes->generate();
        } while (isset($used[$code]));

        $used[$code] = true;

        return $code;
    }

    /**
     * UUID v7 ids drawn now and sorted, so the id order is the creation order (see the class comment).
     *
     * @return list<string>
     */
    private function ids(int $count): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $ids[] = (string) Str::uuid7();
        }

        sort($ids);

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
