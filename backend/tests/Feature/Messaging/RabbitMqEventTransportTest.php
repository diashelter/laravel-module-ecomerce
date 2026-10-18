<?php

use App\Modules\Shared\Messaging\EventEnvelope;
use App\Modules\Shared\Messaging\EventOutbox;
use App\Modules\Shared\Messaging\EventSubscriptions;
use App\Modules\Shared\Messaging\RabbitMq\RabbitMqConnector;
use App\Modules\Shared\Messaging\RabbitMq\RabbitMqEventTransport;
use App\Modules\Shared\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\Feature\Outbox\Fixtures\OtherEvent;
use Tests\Feature\Outbox\Fixtures\OtherEventConsumer;
use Tests\Feature\Outbox\Fixtures\SampleEvent;
use Tests\Feature\Outbox\Fixtures\SecondSpyConsumer;
use Tests\Feature\Outbox\Fixtures\SpyConsumer;

/** Two consumers of the sample event and one of another event, the topology the transport declares. */
function subscribeTopologyConsumers(): void
{
    $subscriptions = app(EventSubscriptions::class);
    $subscriptions->subscribe(SampleEvent::class, SpyConsumer::class, 'testing.spy_consumer');
    $subscriptions->subscribe(SampleEvent::class, SecondSpyConsumer::class, 'testing.second_spy_consumer');
    $subscriptions->subscribe(OtherEvent::class, OtherEventConsumer::class, 'testing.other_consumer');
}

/** Records a sample event the way a use case does, inside a transaction. */
function recordSampleEvent(array $payload = ['order_id' => '0199f0a0-0000-7000-8000-000000000001']): void
{
    DB::transaction(fn () => app(EventOutbox::class)->record(new SampleEvent($payload)));
}

/** @return array<string, mixed> the queue as the management API shows it, once its arguments are readable */
function queueState(string $queue): array
{
    return rabbitApi('queues/'.rabbitVhostPath().'/'.rawurlencode($queue));
}

it('declares the topology before the first message', function () {
    subscribeTopologyConsumers();
    storeFlag('rabbitmq');
    recordSampleEvent();

    expect(relayOnce())->toBe(0);

    $vhost = rabbitVhostPath();
    $exchanges = collect(rabbitApi("exchanges/{$vhost}"))->keyBy('name');

    expect($exchanges['domain_events']['type'])->toBe('topic')
        ->and($exchanges['domain_events']['durable'])->toBeTrue()
        ->and($exchanges['domain_events.dead']['type'])->toBe('direct')
        ->and($exchanges['domain_events.dead']['durable'])->toBeTrue();

    $expectedArguments = [
        'x-queue-type' => 'quorum',
        'x-delivery-limit' => 2,
        'x-dead-letter-exchange' => 'domain_events.dead',
        'x-dead-letter-strategy' => 'at-least-once',
        'x-overflow' => 'reject-publish',
    ];

    foreach (['testing.spy_consumer' => 'testing.sample_event', 'testing.second_spy_consumer' => 'testing.sample_event', 'testing.other_consumer' => 'testing.other_event'] as $name => $event) {
        $queue = queueState($name);
        $arguments = $queue['arguments'];

        expect($queue['type'])->toBe('quorum')
            ->and($queue['durable'])->toBeTrue()
            ->and($arguments)->toHaveCount(6)
            ->and($arguments['x-dead-letter-routing-key'])->toBe($name);

        foreach ($expectedArguments as $argument => $value) {
            expect($arguments[$argument])->toBe($value, "{$name} {$argument}");
        }

        $bindings = collect(rabbitApi("queues/{$vhost}/".rawurlencode($name).'/bindings'))->where('source', '!=', '')->values();
        expect($bindings->map(fn (array $binding) => [$binding['source'], $binding['routing_key']])->all())
            ->toBe([['domain_events', $event]]);

        $dead = queueState("{$name}.dead");
        $deadBindings = collect(rabbitApi("queues/{$vhost}/".rawurlencode("{$name}.dead").'/bindings'))->where('source', '!=', '')->values();

        expect($dead['type'])->toBe('quorum')
            ->and($dead['durable'])->toBeTrue()
            ->and($dead['arguments'])->not->toHaveKeys(['x-message-ttl', 'x-expires'])
            ->and($deadBindings->map(fn (array $binding) => [$binding['source'], $binding['routing_key']])->all())
            ->toBe([['domain_events.dead', $name]]);
    }

    // The messages are in the queues: they existed before the publication.
    expect(readyMessages('testing.spy_consumer'))->toBe(1)
        ->and(readyMessages('testing.second_spy_consumer'))->toBe(1);
});

it('publishes one persistent message with the envelope as json', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-18 15:52:24.715000', 'UTC'));
    subscribeTopologyConsumers();
    storeFlag('rabbitmq');
    Context::add('request_id', 'trace-rabbit-0001');
    $payload = ['order_id' => '0199f0a0-0000-7000-8000-000000000001', 'quantity' => 3];
    recordSampleEvent($payload);
    $row = OutboxMessage::query()->sole();

    expect(relayOnce())->toBe(0);

    $connection = rabbitConnection();
    $message = $connection->channel()->basic_get('testing.spy_consumer', true);
    $connection->close();

    expect($message)->not->toBeNull()
        ->and($message->get('delivery_mode'))->toBe(2)
        ->and($message->get('content_type'))->toBe('application/json')
        ->and($message->get('message_id'))->toBe($row->id)
        ->and($message->get('type'))->toBe('testing.sample_event')
        ->and($message->getRoutingKey())->toBe('testing.sample_event');

    $body = json_decode($message->getBody(), true);

    expect(array_keys($body))->toEqualCanonicalizing(['id', 'name', 'version', 'payload', 'occurred_at', 'request_id'])
        ->and($body['id'])->toBe($row->id)
        ->and($body['name'])->toBe('testing.sample_event')
        ->and($body['version'])->toBe(1)
        ->and($body['payload'])->toBe($payload)
        // The outbox column keeps seconds (HEL-9), so the frozen 715 ms is gone by the time the relay reads the row.
        ->and($body['occurred_at'])->toBe('2026-10-18T15:52:24.000000Z')
        ->and($body['request_id'])->toBe('trace-rabbit-0001');

    // The transport formats whatever the envelope carries: microseconds, and UTC whatever the zone.
    app(RabbitMqEventTransport::class)->publish(new EventEnvelope(
        unknownId(), 'testing.sample_event', 1, $payload, CarbonImmutable::parse('2026-10-18 18:52:24.715000', 'America/Sao_Paulo'), null,
    ));
    $connection = rabbitConnection();
    $second = $connection->channel()->basic_get('testing.spy_consumer', true);
    $connection->close();

    expect(json_decode($second->getBody(), true))
        ->toHaveKey('occurred_at', '2026-10-18T21:52:24.715000Z')
        ->toHaveKey('request_id', null);
});

it('copies the message once into each consumer queue', function () {
    subscribeTopologyConsumers();
    storeFlag('rabbitmq');
    recordSampleEvent();

    expect(relayOnce())->toBe(0);

    expect(readyMessages('testing.spy_consumer'))->toBe(1)
        ->and(readyMessages('testing.second_spy_consumer'))->toBe(1)
        ->and(readyMessages('testing.other_consumer'))->toBe(0);
});

it('publishes an event with no consumer without error', function () {
    subscribeTopologyConsumers();

    app(RabbitMqEventTransport::class)->publish(new EventEnvelope(
        unknownId(), 'testing.unsubscribed_event', 1, [], CarbonImmutable::parse('2026-10-18 12:00:00'), null,
    ));

    foreach (['testing.spy_consumer', 'testing.second_spy_consumer', 'testing.other_consumer'] as $queue) {
        expect(readyMessages($queue))->toBe(0);
    }
});

it('marks the message published once the broker confirms', function () {
    subscribeTopologyConsumers();
    storeFlag('rabbitmq');
    recordSampleEvent();

    expect(relayOnce())->toBe(0);

    expect(OutboxMessage::query()->sole()->published_at)->not->toBeNull();
});

it('keeps the message pending when the broker is unreachable', function () {
    subscribeTopologyConsumers();
    storeFlag('rabbitmq');
    config(['rabbitmq.port' => 1]);
    recordSampleEvent();

    expect(relayOnce())->toBe(1);

    expect(OutboxMessage::query()->sole()->published_at)->toBeNull();
});

it('keeps the message pending when the broker refuses it', function () {
    subscribeTopologyConsumers();
    storeFlag('rabbitmq');
    $connection = rabbitConnection();
    $channel = $connection->channel();
    $channel->exchange_declare('domain_events', 'topic', false, true, false);
    // A queue that holds nothing and refuses to publish, bound to the same event: the broker nacks the message.
    $channel->queue_declare('testing.full', false, true, false, false, false, new AMQPTable([
        'x-max-length' => 0,
        'x-overflow' => 'reject-publish',
    ]));
    $channel->queue_bind('testing.full', 'domain_events', 'testing.sample_event');
    $connection->close();
    recordSampleEvent();

    expect(relayOnce())->toBe(1);

    expect(OutboxMessage::query()->sole()->published_at)->toBeNull();
});

it('throws when the broker does not confirm in five seconds', function () {
    $channel = Mockery::mock(AMQPChannel::class)->shouldIgnoreMissing();
    $channel->shouldReceive('wait_for_pending_acks')->once()->with(5)->andThrow(new AMQPTimeoutException('Timeout waiting on channel'));
    $connection = Mockery::mock(AMQPStreamConnection::class)->shouldIgnoreMissing();
    $connection->shouldReceive('isConnected')->andReturn(true);
    $connection->shouldReceive('channel')->andReturn($channel);
    $connector = Mockery::mock(RabbitMqConnector::class);
    $connector->shouldReceive('connect')->andReturn($connection);
    app()->instance(RabbitMqConnector::class, $connector);

    $publish = fn () => app(RabbitMqEventTransport::class)->publish(new EventEnvelope(
        unknownId(), 'testing.sample_event', 1, [], CarbonImmutable::parse('2026-10-18 12:00:00'), null,
    ));

    expect($publish)->toThrow(AMQPTimeoutException::class);
});
