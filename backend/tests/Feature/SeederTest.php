<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

it('seeds a complete demo environment', function () {
    $this->seed();

    expect(CustomerAccount::query()->count())->toBeGreaterThanOrEqual(10)
        ->and(Category::query()->count())->toBeGreaterThanOrEqual(8)
        ->and(Product::query()->count())->toBeGreaterThanOrEqual(30)
        ->and(Product::query()->doesntHave('stock')->count())->toBe(0)
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
