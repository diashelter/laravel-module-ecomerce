<?php

use App\Modules\Ordering\Enums\OrderStatus;
use App\Modules\Ordering\Models\Order;
use App\Modules\Ordering\Models\OrderItem;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Ordering\ValueObjects\CustomerIds;
use App\Modules\Ordering\ValueObjects\OrderLine;
use App\Modules\Ordering\ValueObjects\OrderLines;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->repository = app(OrderRepository::class));

it('paginates all orders for the admin newest first with customer and item count', function () {
    $older = Order::factory()->create(['created_at' => now()->subDay()]);
    $newer = Order::factory()->create(['created_at' => now()]);
    OrderItem::factory()->count(2)->for($newer)->create();

    $page = $this->repository->paginateForAdmin(15);

    expect($page->pluck('id')->all())->toBe([$newer->id, $older->id])
        ->and($page->first()->relationLoaded('customer'))->toBeTrue()
        ->and($page->first()->items_count)->toBe(2);
});

it('paginates only the orders of the given customer', function () {
    $user = customer();
    $older = Order::factory()->for($user, 'customer')->create(['created_at' => now()->subDay()]);
    $newer = Order::factory()->for($user, 'customer')->create(['created_at' => now()]);
    Order::factory()->create();

    $page = $this->repository->paginateForCustomer($user->id, 10);

    expect($page->pluck('id')->all())->toBe([$newer->id, $older->id])
        ->and($page->total())->toBe(2);
});

it('returns the most recent orders of a customer, using the id as tie breaker', function () {
    $user = customer();
    $orders = Order::factory()->count(4)->for($user, 'customer')->create(['created_at' => now()]);

    $recent = $this->repository->recentForCustomer($user->id, 3);

    expect($recent->pluck('id')->all())->toBe($orders->pluck('id')->reverse()->take(3)->values()->all())
        ->and($recent->first()->items_count)->toBe(0);
});

it('counts the orders of a customer', function () {
    $user = customer();
    Order::factory()->count(3)->for($user, 'customer')->create();
    Order::factory()->create();

    expect($this->repository->countForCustomer($user->id))->toBe(3);
});

it('tells the catalog whether a product was ever ordered', function () {
    $sold = productWithStock(1);
    $unsold = productWithStock(1);
    OrderItem::factory()->forProduct($sold, 1)->create();

    expect($this->repository->hasBeenOrdered($sold->id))->toBeTrue()
        ->and($this->repository->hasBeenOrdered($unsold->id))->toBeFalse();
});

it('creates an order with its items', function () {
    $user = customer();
    $product = productWithStock(5, ['name' => 'Fone', 'price_cents' => 5000]);

    $order = $this->repository->createWithItems($user->id, OrderStatus::Placed, new OrderLines(
        new OrderLine($product->id, 'Fone', 5000, 2),
    ));

    expect($order->customer_id)->toBe($user->id)
        ->and($order->status)->toBe(OrderStatus::Placed)
        ->and($order->total_cents)->toBe(10000)
        ->and($order->items()->count())->toBe(1)
        ->and($order->items()->first()->subtotal_cents)->toBe(10000);
});

it('counts the orders of several customers in a single query', function () {
    $buyer = customer();
    $other = customer();
    $withoutOrders = customer();
    Order::factory()->count(3)->for($buyer, 'customer')->create();
    Order::factory()->for($other, 'customer')->create();
    Order::factory()->create();

    $counts = $this->repository->countPerCustomer(new CustomerIds($buyer->id, $other->id, $withoutOrders->id));

    expect(iterator_to_array($counts))->toEqualCanonicalizing([$buyer->id => 3, $other->id => 1])
        ->and($counts->countFor($withoutOrders->id))->toBe(0);
});

it('counts orders per day since a date', function () {
    $today = Carbon::today();
    Order::factory()->count(2)->create(['created_at' => $today->copy()->addHour()]);
    Order::factory()->create(['created_at' => $today->copy()->subDay()->addHour()]);
    Order::factory()->create(['created_at' => $today->copy()->subDays(10)]);

    $counts = $this->repository->countPerDaySince($today->copy()->subDay());

    expect($counts->map(fn ($total) => (int) $total)->sortKeys()->all())->toBe([
        $today->copy()->subDay()->toDateString() => 1,
        $today->toDateString() => 2,
    ]);
});

it('counts orders per month since a date', function () {
    $thisMonth = Carbon::today()->startOfMonth();
    Order::factory()->count(2)->create(['created_at' => $thisMonth->copy()->addDay()]);
    Order::factory()->create(['created_at' => $thisMonth->copy()->subMonth()->addDay()]);
    Order::factory()->create(['created_at' => $thisMonth->copy()->subMonths(5)]);

    $counts = $this->repository->countPerMonthSince($thisMonth->copy()->subMonth());

    expect($counts->map(fn ($total) => (int) $total)->sortKeys()->all())->toBe([
        $thisMonth->copy()->subMonth()->format('Y-m') => 1,
        $thisMonth->format('Y-m') => 2,
    ]);
});

it('moves an order to the new status when it is in the expected one', function () {
    $order = Order::factory()->status(OrderStatus::Placed)->create();

    $moved = $this->repository->transitionStatus($order, OrderStatus::Placed, OrderStatus::AwaitingPayment);

    expect($moved)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

it('does nothing when the order is not in the expected status', function () {
    $order = Order::factory()->status(OrderStatus::Delivered)->create();

    $moved = $this->repository->transitionStatus($order, OrderStatus::Placed, OrderStatus::AwaitingPayment);

    expect($moved)->toBeFalse()
        ->and($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->fresh()->status)->toBe(OrderStatus::Delivered);
});
