<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Modules\Fulfillment\Persistence\Models\Delivery;
use Modules\Messaging\Contracts\EventSubscriptions;
use Modules\Messaging\Core\RabbitMq\RabbitMqConnector;
use Modules\Ordering\Core\Enums\OrderStatus;
use Modules\Ordering\Events\OrderPaid;
use Modules\Ordering\Persistence\Models\Order;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\Feature\Outbox\Fixtures\FailingConsumer;
use Tests\Feature\Outbox\Fixtures\SampleEvent;
use Tests\Feature\Outbox\Fixtures\SpyConsumer;

/** Subscribes the spy under its name; the failing consumer, when asked, to the same event. */
function subscribeSpy(bool $withFailing = false): void
{
    $subscriptions = app(EventSubscriptions::class);
    $subscriptions->subscribe(SampleEvent::class, SpyConsumer::class, 'testing.spy_consumer');

    if ($withFailing) {
        $subscriptions->subscribe(SampleEvent::class, FailingConsumer::class, 'testing.failing_consumer');
    }
}

/** The queue names of the vhost that are not the given ones. */
function queuesOtherThan(string ...$names): array
{
    return array_values(array_diff(brokerObjects()['queues'], $names));
}

it('delivers a message to its consumer once and acknowledges it after the handle', function () {
    subscribeSpy();
    $spy = bindSpy();
    $envelope = brokerEnvelope();
    publishToBroker($envelope);
    $unackedDuring = null;
    $spy->during = function () use (&$unackedDuring) {
        $unackedDuring = awaitQueue('testing.spy_consumer', fn (array $queue) => ($queue['messages_unacknowledged'] ?? 0) === 1)['messages_unacknowledged'];
    };

    expect(consumeUntilEmpty())->toBe(0);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['event'])->toEqual(SampleEvent::fromPayload($envelope->payload))
        ->and($unackedDuring)->toBe(1);

    $queue = awaitQueue('testing.spy_consumer', fn (array $queue) => ($queue['messages'] ?? 1) === 0);

    expect($queue['messages_ready'])->toBe(0)
        ->and($queue['messages_unacknowledged'])->toBe(0);
});

it('restores the envelope request id in the consumer process', function () {
    subscribeSpy();
    $spy = bindSpy();
    publishToBroker(brokerEnvelope(['requestId' => 'trace-consume-0001']));

    expect(consumeUntilEmpty())->toBe(0);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['request_id'])->toBe('trace-consume-0001');
});

it('clears a stale request id in the consumer process', function () {
    subscribeSpy();
    $spy = bindSpy();
    publishToBroker(brokerEnvelope(['requestId' => null]));
    Context::add('request_id', 'stale-0001');

    expect(consumeUntilEmpty())->toBe(0);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['request_id'])->toBeNull();
});

it('moves a message to the dead queue after three failed deliveries', function () {
    subscribeSpy(withFailing: true);
    bindSpy();
    $failing = new FailingConsumer;
    app()->instance(FailingConsumer::class, $failing);
    $envelope = brokerEnvelope();
    publishToBroker($envelope);

    expect(consumeUntilEmpty())->toBe(0);

    expect($failing->attempts)->toBe(3);

    awaitReadyMessages('testing.failing_consumer.dead', 1);
    $connection = rabbitConnection();
    $dead = $connection->channel()->basic_get('testing.failing_consumer.dead', true);
    $connection->close();

    expect($dead->get('message_id'))->toBe($envelope->id)
        ->and(queuesOtherThan('testing.failing_consumer.dead'))->not->toBeEmpty();

    foreach (queuesOtherThan('testing.failing_consumer.dead') as $queue) {
        expect(readyMessages($queue))->toBe(0, $queue);
    }
});

it('logs every failed delivery with its attempt', function () {
    subscribeSpy(withFailing: true);
    bindSpy();
    app()->instance(FailingConsumer::class, new FailingConsumer);
    $envelope = brokerEnvelope();
    publishToBroker($envelope);
    Log::spy();

    consumeUntilEmpty();

    $lines = [];
    Log::shouldHaveReceived('warning')->times(3)->withArgs(function (string $message, array $context) use (&$lines) {
        $lines[] = $context;

        return true;
    });

    expect($lines)->toBe([
        ['message_id' => $envelope->id, 'name' => 'testing.sample_event', 'consumer' => 'testing.failing_consumer', 'attempt' => 1, 'error' => 'consumer down'],
        ['message_id' => $envelope->id, 'name' => 'testing.sample_event', 'consumer' => 'testing.failing_consumer', 'attempt' => 2, 'error' => 'consumer down'],
        ['message_id' => $envelope->id, 'name' => 'testing.sample_event', 'consumer' => 'testing.failing_consumer', 'attempt' => 3, 'error' => 'consumer down'],
    ]);
});

it('never calls the consumer for an envelope it cannot read', function (string $body) {
    subscribeSpy();
    $spy = bindSpy();
    publishToBroker(brokerEnvelope());
    $connection = rabbitConnection();
    $channel = $connection->channel();
    $channel->queue_purge('testing.spy_consumer');
    $channel->basic_publish(new AMQPMessage($body, ['content_type' => 'application/json', 'delivery_mode' => 2]), 'domain_events', 'testing.sample_event');
    $connection->close();

    expect(consumeUntilEmpty())->toBe(0);

    expect($spy->calls)->toBe([]);
    awaitReadyMessages('testing.spy_consumer.dead', 1);
})->with([
    'version 2' => [fn () => json_encode(['id' => unknownId(), 'name' => 'testing.sample_event', 'version' => 2, 'payload' => [], 'occurred_at' => '2026-10-18T12:00:00.000000Z', 'request_id' => null])],
    'not json' => ['not json'],
    'no payload' => [fn () => json_encode(['id' => unknownId(), 'name' => 'testing.sample_event', 'version' => 1, 'occurred_at' => '2026-10-18T12:00:00.000000Z', 'request_id' => null])],
    'empty list' => ['[]'],
]);

it('counts a closed connection as a failed delivery', function () {
    subscribeSpy();
    publishToBroker(brokerEnvelope());

    foreach ([1, 2] as $closing) {
        $connection = rabbitConnection();
        expect($connection->channel()->basic_get('testing.spy_consumer', false))->not->toBeNull();
        $connection->close();

        awaitReadyMessages('testing.spy_consumer', 1);
    }

    $connection = rabbitConnection();
    expect($connection->channel()->basic_get('testing.spy_consumer', false))->not->toBeNull();
    $connection->close();

    awaitReadyMessages('testing.spy_consumer.dead', 1);
    expect(readyMessages('testing.spy_consumer'))->toBe(0);
});

it('holds one unacknowledged delivery at a time', function () {
    subscribeSpy();
    $spy = bindSpy();

    foreach ([1, 2, 3] as $number) {
        publishToBroker(brokerEnvelope());
    }

    $unacked = [];
    $prefetch = null;
    $spy->during = function () use (&$unacked, &$prefetch) {
        $unacked[] = awaitQueue('testing.spy_consumer', fn (array $queue) => ($queue['messages_unacknowledged'] ?? 0) >= 1)['messages_unacknowledged'];
        $prefetch ??= collect(rabbitApi('consumers/'.rabbitVhostPath()))
            ->firstWhere('queue.name', 'testing.spy_consumer')['prefetch_count'] ?? null;
    };

    expect(consumeUntilEmpty())->toBe(0);

    expect($unacked)->toBe([1, 1, 1])
        ->and($prefetch)->toBe(1);
});

it('retries the broker every five seconds until it connects', function () {
    subscribeSpy();
    $spy = bindSpy();
    publishToBroker(brokerEnvelope());
    $connector = new class extends RabbitMqConnector
    {
        public int $attempts = 0;

        public function connect(): AMQPStreamConnection
        {
            if (++$this->attempts <= 2) {
                throw new RuntimeException('RabbitMQ is unreachable (test)');
            }

            return parent::connect();
        }
    };
    app()->instance(RabbitMqConnector::class, $connector);
    Sleep::fake();

    expect(consumeUntilEmpty())->toBe(0);

    Sleep::assertSleptTimes(2);
    Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(5)->seconds()]);
    expect($connector->attempts)->toBe(3)
        ->and($spy->calls)->toHaveCount(1);
});

it('stops after the delivery in progress when the restart signal changes', function () {
    subscribeSpy();
    $spy = bindSpy();
    publishToBroker(brokerEnvelope());
    publishToBroker(brokerEnvelope());
    $spy->during = fn () => Cache::forever('illuminate:queue:restart', 'changed');

    expect(Artisan::call('events:consume'))->toBe(0);

    expect($spy->calls)->toHaveCount(1);

    awaitReadyMessages('testing.spy_consumer', 1);
});

it('consumes the queued messages while the flag is redis', function () {
    subscribeSpy();
    $spy = bindSpy();
    publishToBroker(brokerEnvelope());
    storeFlag('redis');

    expect(consumeUntilEmpty())->toBe(0);

    expect($spy->calls)->toHaveCount(1);
});

it('declares the topology when it boots', function () {
    subscribeSpy(withFailing: true);

    expect(brokerObjects()['queues'])->toBe([]);

    expect(consumeUntilEmpty())->toBe(0);

    expect(brokerObjects()['queues'])->toContain('testing.spy_consumer', 'testing.spy_consumer.dead', 'testing.failing_consumer', 'testing.failing_consumer.dead');
});

it('connects again after it loses the connection while consuming', function () {
    subscribeSpy();
    $spy = bindSpy();
    publishToBroker(brokerEnvelope());
    $connector = new class extends RabbitMqConnector
    {
        public ?AMQPStreamConnection $last = null;

        public function connect(): AMQPStreamConnection
        {
            return $this->last = parent::connect();
        }
    };
    app()->instance(RabbitMqConnector::class, $connector);
    $dropped = false;
    // The socket closes while the delivery is in progress: the acknowledgement cannot be sent.
    $spy->during = function () use ($connector, &$dropped) {
        if (! $dropped) {
            $dropped = true;
            $connector->last->getIO()->close();
        }
    };
    Sleep::fake();

    expect(consumeUntilEmpty())->toBe(0);

    // The unacknowledged delivery went back to the queue and was delivered again, after one 5 s wait.
    expect($spy->calls)->toHaveCount(2);
    Sleep::assertSleptTimes(1);
    Sleep::assertSequence([Sleep::for(5)->seconds()]);
    expect(readyMessages('testing.spy_consumer'))->toBe(0);
});

it('consumes OrderPaid twice from rabbitmq without announcing twice', function () {
    // A requeue after a lost acknowledgement hands the consumer the same envelope again.
    storeFlag('rabbitmq');
    Queue::fake();
    $order = Order::factory()->status(OrderStatus::PaymentApproved)->create();
    $envelope = envelopeOf(new OrderPaid($order->id, 2));
    publishToBroker($envelope);
    publishToBroker($envelope);

    expect(consumeUntilEmpty())->toBe(0);

    expect(Delivery::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(outboxMessages('fulfillment.delivery_scheduled'))->toHaveCount(1)
        ->and(DB::table('outbox_messages')->count())->toBe(1);
});
