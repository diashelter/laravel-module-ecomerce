<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Fulfillment\Core\Consumers\ScheduleOrderDelivery;
use Modules\Fulfillment\Core\Jobs\DeliverOrder;
use Modules\Fulfillment\Persistence\Models\Delivery;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Events\OrderPaid;
use Modules\Ordering\Persistence\Models\Order;
use Tests\Feature\Outbox\Fixtures\FailingOutbox;

/**
 * DeliverOrder is a queue job, so it runs again on a retry and may be queued twice: only the first
 * run that finds the delivery pending announces it.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'UTC'));
    $this->order = Order::factory()->status(OrderStatus::PaymentApproved)->create();
});

it('delivers an order once and announces it', function () {
    Delivery::factory()->create(['order_id' => $this->order->id]);

    (new DeliverOrder($this->order->id))->handle();

    expect(Delivery::query()->sole()->delivered_at?->equalTo(now()))->toBeTrue()
        ->and(outboxMessages('fulfillment.order_delivered'))->toHaveCount(1)
        ->and(outboxMessages('fulfillment.order_delivered')->sole()->payload)->toEqual(['order_id' => $this->order->id]);
});

it('keeps the first delivered_at and announces nothing on a second run', function () {
    Delivery::factory()->create(['order_id' => $this->order->id]);
    $firstRun = now();

    (new DeliverOrder($this->order->id))->handle();
    $this->travel(1)->days();
    (new DeliverOrder($this->order->id))->handle();

    expect(Delivery::query()->sole()->delivered_at->equalTo($firstRun))->toBeTrue()
        ->and(outboxMessages('fulfillment.order_delivered'))->toHaveCount(1)
        ->and(DB::table('outbox_messages')->count())->toBe(1);
});

it('rolls back delivered_at when the event cannot be recorded', function () {
    Delivery::factory()->create(['order_id' => $this->order->id]);
    app()->instance(EventOutbox::class, new FailingOutbox('fulfillment.order_delivered'));

    expect(fn () => (new DeliverOrder($this->order->id))->handle())
        ->toThrow(RuntimeException::class, 'outbox down');

    expect(Delivery::query()->sole()->delivered_at)->toBeNull()
        ->and(DB::table('outbox_messages')->count())->toBe(0);

    app()->forgetInstance(EventOutbox::class);
    (new DeliverOrder($this->order->id))->handle();

    expect(Delivery::query()->sole()->delivered_at)->not->toBeNull()
        ->and(outboxMessages('fulfillment.order_delivered'))->toHaveCount(1);
});

it('changes nothing for an order without a delivery row', function () {
    (new DeliverOrder($this->order->id))->handle();

    expect(Delivery::query()->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0);
});

it('leaves the order row untouched', function () {
    $before = (array) DB::table('orders')->where('id', $this->order->id)->first();

    // The sync queue runs the DeliverOrder inside the scheduling, so both Fulfillment steps happen.
    deliverEnvelope(envelopeOf(new OrderPaid($this->order->id, 2)), ScheduleOrderDelivery::class);

    expect(Delivery::query()->sole()->delivered_at)->not->toBeNull()
        ->and((array) DB::table('orders')->where('id', $this->order->id)->first())->toBe($before);
});
