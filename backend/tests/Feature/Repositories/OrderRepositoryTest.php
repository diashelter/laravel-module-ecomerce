<?php

use Modules\Ordering\Contracts\CustomerOrderHistory;
use Modules\Ordering\Contracts\PayableOrders;
use Modules\Ordering\Core\Enums\BrazilianState;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Core\ValueObjects\CustomerIds;
use Modules\Ordering\Core\ValueObjects\DeliveryAddress;
use Modules\Ordering\Core\ValueObjects\OrderForPayment;
use Modules\Ordering\Core\ValueObjects\OrderLine;
use Modules\Ordering\Core\ValueObjects\OrderLines;
use Modules\Ordering\Core\ValueObjects\OrderSummaries;
use Modules\Ordering\Core\ValueObjects\OrderSummary;
use Modules\Ordering\Core\ValueObjects\ShippingQuote;
use Modules\Ordering\Persistence\Models\Order;
use Modules\Ordering\Persistence\Models\OrderItem;
use Modules\Ordering\Persistence\Repositories\OrderRepository;

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

    $recent = app(CustomerOrderHistory::class)->recentForCustomer($user->id, 3);
    $newest = $orders->last();

    // The summary carries what the account screens show, not the items.
    expect($recent)->toBeInstanceOf(OrderSummaries::class)
        ->and(array_map(fn (OrderSummary $summary) => $summary->id, iterator_to_array($recent)))->toBe($orders->pluck('id')->reverse()->take(3)->values()->all())
        ->and($recent->first())->toEqual(new OrderSummary($newest->id, $newest->code, $newest->status, $newest->total_cents, $newest->created_at->toImmutable()));
});

it('finds an order for payment only among the customer own orders', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->status(OrderStatus::AwaitingPayment)->create(['total_cents' => 15990]);

    expect(app(PayableOrders::class)->findForPayment($user->id, $order->id))
        ->toEqual(new OrderForPayment($order->id, $user->id, 15990, OrderStatus::AwaitingPayment))
        ->and(app(PayableOrders::class)->findForPayment(customer()->id, $order->id))->toBeNull()
        ->and(app(PayableOrders::class)->findForPayment($user->id, unknownId()))->toBeNull();
});

it('finds an order only among the customer own orders', function () {
    $user = customer();
    $order = Order::factory()->for($user, 'customer')->create();
    OrderItem::factory()->for($order)->create();

    $found = $this->repository->findForCustomer($user->id, $order->id);

    expect($found->id)->toBe($order->id)
        ->and($found->relationLoaded('items'))->toBeTrue()
        ->and($found->items)->toHaveCount(1)
        ->and($this->repository->findForCustomer(customer()->id, $order->id))->toBeNull()
        ->and($this->repository->findForCustomer($user->id, unknownId()))->toBeNull();
});

it('counts the orders of a customer', function () {
    $user = customer();
    Order::factory()->count(3)->for($user, 'customer')->create();
    Order::factory()->create();

    expect(app(CustomerOrderHistory::class)->countForCustomer($user->id))->toBe(3);
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

    $order = $this->repository->createWithItems(
        $user->id,
        'K7Q2M9XA',
        OrderStatus::Placed,
        new OrderLines(new OrderLine($product->id, 'Fone', 5000, 2)),
        new DeliveryAddress('Ana Souza', '01310100', 'Avenida Paulista', '1000', null, 'Bela Vista', 'São Paulo', BrazilianState::SP),
        new ShippingQuote(1500, 2),
    );

    expect($order->customer_id)->toBe($user->id)
        ->and($order->code)->toBe('K7Q2M9XA')
        ->and($order->status)->toBe(OrderStatus::Placed)
        ->and($order->total_cents)->toBe(10000 + 1500)
        ->and($order->shipping_cents)->toBe(1500)
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
