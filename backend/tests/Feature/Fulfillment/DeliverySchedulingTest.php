<?php

use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Fulfillment\Core\Consumers\ScheduleOrderDelivery;
use Modules\Fulfillment\Core\Jobs\DeliverOrder;
use Modules\Fulfillment\Events\DeliveryScheduled;
use Modules\Fulfillment\Persistence\Models\Delivery;
use Modules\Messaging\Contracts\EventOutbox;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Events\OrderPaid;
use Modules\Ordering\Persistence\Models\Order;
use Tests\Feature\Outbox\Fixtures\FailingOutbox;

/**
 * The same OrderPaid envelope reaches ScheduleOrderDelivery more than once (the delivery is at least
 * once). Every test delivers through EventDelivery, as the DeliverEvent job and events:consume do.
 * The jobs go to the database queue so the tests can count them and read their delay.
 */
beforeEach(function () {
    config(['queue.default' => 'database', 'shop.delivery_delay_seconds' => 90]);
    // Wednesday: two business days later is Friday 2026-10-09.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'America/Sao_Paulo'));
    $this->order = Order::factory()->status(OrderStatus::PaymentApproved)->create();
    $this->paid = envelopeOf(new OrderPaid($this->order->id, 2));
});

function queuedDeliveries(): int
{
    return DB::table('jobs')->count();
}

it('schedules the first OrderPaid of an order', function () {
    deliverEnvelope($this->paid, ScheduleOrderDelivery::class);

    $delivery = Delivery::query()->where('order_id', $this->order->id)->sole();

    expect($delivery->estimated_delivery_on->toDateString())->toBe('2026-10-09')
        ->and($delivery->delivered_at)->toBeNull()
        ->and(Delivery::query()->count())->toBe(1)
        ->and(outboxMessages('fulfillment.delivery_scheduled'))->toHaveCount(1)
        ->and(outboxMessages('fulfillment.delivery_scheduled')->sole()->payload)
        ->toEqual(['order_id' => $this->order->id, 'estimated_delivery_on' => '2026-10-09']);
});

it('schedules the first OrderPaid from the business days the event carries', function () {
    // The stored value differs on purpose: the schedule comes from the event alone.
    $this->order->update(['delivery_business_days' => 9]);

    deliverEnvelope(envelopeOf(new OrderPaid($this->order->id, 3)), ScheduleOrderDelivery::class);

    expect(Delivery::query()->sole()->estimated_delivery_on->toDateString())->toBe('2026-10-12');
});

it('queues the delivery after the commit with the configured delay', function () {
    $baseLevel = DB::transactionLevel();
    $seen = [];
    Event::listen(JobQueueing::class, function (JobQueueing $queueing) use (&$seen) {
        $seen[] = [
            'job' => $queueing->job::class,
            'level' => DB::transactionLevel(),
            'rows' => Delivery::query()->count(),
            'messages' => outboxMessages('fulfillment.delivery_scheduled')->count(),
        ];
    });

    deliverEnvelope($this->paid, ScheduleOrderDelivery::class);

    expect($seen)->toBe([['job' => DeliverOrder::class, 'level' => $baseLevel, 'rows' => 1, 'messages' => 1]]);

    $job = DB::table('jobs')->sole();
    expect(json_decode($job->payload, true)['displayName'])->toBe(DeliverOrder::class)
        ->and((int) $job->available_at)->toBe(now()->addSeconds(90)->getTimestamp());
});

it('repeats nothing but the queued job for an undelivered order', function () {
    deliverEnvelope($this->paid, ScheduleOrderDelivery::class);
    $this->travel(1)->days();

    deliverEnvelope($this->paid, ScheduleOrderDelivery::class);

    expect(Delivery::query()->count())->toBe(1)
        ->and(Delivery::query()->sole()->estimated_delivery_on->toDateString())->toBe('2026-10-09')
        ->and(outboxMessages('fulfillment.delivery_scheduled'))->toHaveCount(1)
        ->and(DB::table('outbox_messages')->count())->toBe(1)
        ->and(queuedDeliveries())->toBe(2);
});

it('does nothing for an order already delivered', function () {
    Delivery::factory()->delivered()->create(['order_id' => $this->order->id]);

    deliverEnvelope($this->paid, ScheduleOrderDelivery::class);

    expect(Delivery::query()->count())->toBe(1)
        ->and(DB::table('outbox_messages')->count())->toBe(0)
        ->and(queuedDeliveries())->toBe(0);
});

it('rolls back the row when the event cannot be recorded', function () {
    app()->instance(EventOutbox::class, new FailingOutbox('fulfillment.delivery_scheduled'));

    expect(fn () => deliverEnvelope($this->paid, ScheduleOrderDelivery::class))
        ->toThrow(RuntimeException::class, 'outbox down');

    expect(Delivery::query()->count())->toBe(0)
        ->and(DB::table('outbox_messages')->count())->toBe(0)
        ->and(queuedDeliveries())->toBe(0);

    app()->forgetInstance(EventOutbox::class);
    deliverEnvelope($this->paid, ScheduleOrderDelivery::class);

    expect(Delivery::query()->count())->toBe(1)
        ->and(outboxMessages('fulfillment.delivery_scheduled'))->toHaveCount(1)
        ->and(queuedDeliveries())->toBe(1);
});

it('keeps the row and the event when queueing fails and repairs on redelivery', function () {
    $queueDown = true;
    Event::listen(JobQueueing::class, function () use (&$queueDown) {
        if ($queueDown) {
            throw new RuntimeException('queue down');
        }
    });

    expect(fn () => deliverEnvelope($this->paid, ScheduleOrderDelivery::class))
        ->toThrow(RuntimeException::class, 'queue down');

    expect(Delivery::query()->count())->toBe(1)
        ->and(outboxMessages('fulfillment.delivery_scheduled'))->toHaveCount(1)
        ->and(queuedDeliveries())->toBe(0);

    $queueDown = false;
    deliverEnvelope($this->paid, ScheduleOrderDelivery::class);

    expect(Delivery::query()->count())->toBe(1)
        ->and(outboxMessages('fulfillment.delivery_scheduled'))->toHaveCount(1)
        ->and(queuedDeliveries())->toBe(1);
});

it('loses a race on the same order cleanly', function () {
    // The rival delivery commits its row and its event between the read and the write of this one.
    // Both run on the test connection, since the test transaction hides rows from other processes.
    $racing = false;
    DB::beforeExecuting(function (string $query) use (&$racing) {
        if ($racing || ! str_contains($query, 'into "deliveries"')) {
            return;
        }

        $racing = true;
        DB::transaction(function () {
            Delivery::factory()->create(['order_id' => $this->order->id, 'estimated_delivery_on' => '2026-10-09']);
            app(EventOutbox::class)->record(new DeliveryScheduled($this->order->id, CarbonImmutable::parse('2026-10-09', 'America/Sao_Paulo')));
        });
    });

    deliverEnvelope($this->paid, ScheduleOrderDelivery::class);

    expect($racing)->toBeTrue()
        ->and(Delivery::query()->count())->toBe(1)
        ->and(outboxMessages('fulfillment.delivery_scheduled'))->toHaveCount(1)
        ->and(queuedDeliveries())->toBe(1);
});
