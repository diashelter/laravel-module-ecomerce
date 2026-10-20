<?php

use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Customers\Persistence\Models\CustomerAddress;
use Modules\Identity\Core\Enums\UserRole;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Identity\Persistence\Models\User;
use Modules\Inventory\Persistence\Models\Stock;
use Modules\Ordering\Contracts\ShippingQuoter;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Persistence\Models\Order;

it('seeds a complete demo environment', function () {
    $this->seed();

    expect(CustomerAccount::query()->count())->toBeGreaterThanOrEqual(10)
        ->and(Category::query()->count())->toBeGreaterThanOrEqual(8)
        ->and(Product::query()->count())->toBeGreaterThanOrEqual(30)
        ->and(Product::query()->whereNotIn('id', Stock::query()->select('product_id'))->count())->toBe(0)
        ->and(Product::query()->doesntHave('categories')->count())->toBe(0)
        ->and(Stock::query()->where('quantity', 0)->count())->toBeGreaterThanOrEqual(5)
        ->and(Stock::query()->whereBetween('quantity', [1, 5])->count())->toBeGreaterThanOrEqual(5)
        ->and(Stock::query()->where('quantity', '>', 20)->count())->toBeGreaterThan(0)
        ->and(Order::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(Order::query()->where('created_at', '>=', now()->subDays(30))->count())->toBeGreaterThan(0)
        ->and(Order::query()->where('created_at', '<', now()->subDays(30))->count())->toBeGreaterThan(0)
        ->and(Order::query()->doesntHave('items')->count())->toBe(0);

    foreach (OrderStatus::cases() as $status) {
        expect(Order::query()->where('status', $status)->exists())->toBeTrue();
    }

    expect(Product::query()->orderBy('id')->first()->image_url)->toBe('https://picsum.photos/seed/product-1/600/600');
});

it('seeds the admin and the support staff members', function () {
    $this->seed();

    $staff = User::query()->orderBy('email')->get();

    expect($staff)->toHaveCount(2)
        ->and($staff->pluck('role', 'email')->all())->toBe(['admin@example.com' => UserRole::Admin, 'suporte@example.com' => UserRole::Support])
        ->and($staff->every(fn (User $member) => Hash::check('password', $member->password)))->toBeTrue();
});

it('seeds the demo customers into the customers table', function () {
    $this->seed();

    $demo = CustomerAccount::query()->where('email', 'cliente@example.com')->sole();

    expect(CustomerAccount::query()->count())->toBe(UserSeeder::CUSTOMERS)
        ->and(Hash::check('password', $demo->password))->toBeTrue()
        ->and(User::query()->where('email', 'cliente@example.com')->exists())->toBeFalse();
});

it('produces the same data on every run', function () {
    $this->seed();
    $first = Product::query()->orderBy('id')->pluck('name')->all();

    $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true]);

    expect(Product::query()->orderBy('id')->pluck('name')->all())->toBe($first);
});

it('seeds an address for every customer', function () {
    $this->seed();

    $demo = CustomerAccount::query()->where('email', 'cliente@example.com')->sole();

    expect(CustomerAccount::query()->whereNotIn('id', CustomerAddress::query()->select('customer_id'))->count())->toBe(0)
        ->and(CustomerAddress::query()->where('customer_id', $demo->id)->where('state', 'SP')->exists())->toBeTrue();
});

it('seeds orders with the delivery copy and the shipping of the rate table', function () {
    $this->seed();

    $quoter = app(ShippingQuoter::class);

    foreach (Order::query()->with('items')->get() as $order) {
        $quote = $quoter->quote($order->delivery_state);

        expect(CustomerAddress::query()
            ->where('customer_id', $order->customer_id)
            ->where('recipient_name', $order->delivery_recipient_name)
            ->where('postal_code', $order->delivery_postal_code)
            ->where('street', $order->delivery_street)
            ->where('number', $order->delivery_number)
            ->where('complement', $order->delivery_complement)
            ->where('district', $order->delivery_district)
            ->where('city', $order->delivery_city)
            ->where('state', $order->delivery_state)
            ->exists())->toBeTrue()
            ->and($order->shipping_cents)->toBe($quote->priceCents)
            ->and($order->delivery_business_days)->toBe($quote->deliveryBusinessDays)
            ->and($order->total_cents)->toBe($order->items->sum('subtotal_cents') + $order->shipping_cents);
    }
});

it('seeds the delivery estimate only for paid orders', function () {
    $this->seed();

    foreach ([OrderStatus::PaymentApproved, OrderStatus::Delivered] as $paid) {
        expect(Order::query()->where('status', $paid)->exists())->toBeTrue()
            ->and(Order::query()->where('status', $paid)->whereNull('estimated_delivery_on')->count())->toBe(0);
    }

    foreach ([OrderStatus::Placed, OrderStatus::AwaitingPayment] as $unpaid) {
        expect(Order::query()->where('status', $unpaid)->exists())->toBeTrue()
            ->and(Order::query()->where('status', $unpaid)->whereNotNull('estimated_delivery_on')->count())->toBe(0);
    }
});
