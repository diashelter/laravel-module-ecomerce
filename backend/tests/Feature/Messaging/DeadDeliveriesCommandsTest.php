<?php

use App\Modules\Shared\Messaging\EventEnvelope;
use App\Modules\Shared\Messaging\EventSubscriptions;
use App\Modules\Shared\Messaging\RabbitMq\DeadDeliveries;
use App\Modules\Shared\Messaging\RabbitMq\RabbitMqConnector;
use App\Modules\Shared\Messaging\RabbitMq\RabbitMqEnvelope;
use App\Modules\Shared\Messaging\RabbitMq\RabbitMqTopology;
use Illuminate\Support\Facades\Artisan;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\Feature\Outbox\Fixtures\FailingConsumer;
use Tests\Feature\Outbox\Fixtures\SampleEvent;
use Tests\Feature\Outbox\Fixtures\SecondFailingConsumer;
use Tests\Feature\Outbox\Fixtures\SpyConsumer;

/** The consumers a dead delivery can belong to, all of the sample event. */
function subscribeDeadConsumers(): void
{
    $subscriptions = app(EventSubscriptions::class);
    $subscriptions->subscribe(SampleEvent::class, SpyConsumer::class, 'testing.spy_consumer');
    $subscriptions->subscribe(SampleEvent::class, FailingConsumer::class, 'testing.failing_consumer');
    $subscriptions->subscribe(SampleEvent::class, SecondFailingConsumer::class, 'testing.second_failing_consumer');
}

/** Puts the envelope in the dead queue of the consumer, as the broker does after the third failure. */
function killDelivery(string $consumer, EventEnvelope $envelope): void
{
    $connection = rabbitConnection();
    $channel = $connection->channel();
    app(RabbitMqTopology::class)->declare($channel);
    $connection->close();
    $before = readyMessages("{$consumer}.dead");

    $connection = rabbitConnection();
    $connection->channel()->basic_publish(RabbitMqEnvelope::toMessage($envelope), RabbitMqTopology::DEAD_EXCHANGE, $consumer);
    $connection->close();
    awaitReadyMessages("{$consumer}.dead", $before + 1);
}

/** The message ids waiting in the queue, read and put back. */
function idsIn(string $queue): array
{
    $connection = rabbitConnection();
    $channel = $connection->channel();
    $ids = [];

    while (($message = $channel->basic_get($queue, false)) !== null) {
        $ids[] = $message->get('message_id');
    }

    $connection->close();
    awaitReadyMessages($queue, count($ids));

    return $ids;
}

it('reports no dead delivery', function () {
    subscribeDeadConsumers();

    $exitCode = Artisan::call('events:failed');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Nenhuma entrega morta.');
});

it('lists every dead delivery without taking it from the queue', function () {
    subscribeDeadConsumers();
    $envelope = brokerEnvelope(['requestId' => 'trace-dead-0001']);
    killDelivery('testing.failing_consumer', $envelope);
    killDelivery('testing.second_failing_consumer', $envelope);

    $exitCode = Artisan::call('events:failed');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);

    foreach (['testing.failing_consumer', 'testing.second_failing_consumer'] as $consumer) {
        $line = collect(explode("\n", $output))->first(fn (string $line) => str_contains($line, $consumer));

        expect($line)->not->toBeNull()
            ->and($line)->toContain('testing.sample_event')
            ->toContain($envelope->id)
            ->toContain('2026-10-18T12:00:00.000000Z')
            ->toContain('trace-dead-0001')
            ->toContain('3');
        expect(idsIn("{$consumer}.dead"))->toBe([$envelope->id]);
    }
});

it('returns every dead delivery to its own consumer queue', function () {
    subscribeDeadConsumers();
    killDelivery('testing.failing_consumer', brokerEnvelope());

    $exitCode = Artisan::call('events:retry');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('testing.failing_consumer: 1');
    awaitReadyMessages('testing.failing_consumer', 1);

    foreach (['testing.spy_consumer', 'testing.second_failing_consumer', 'testing.failing_consumer.dead', 'testing.spy_consumer.dead', 'testing.second_failing_consumer.dead'] as $queue) {
        expect(readyMessages($queue))->toBe(0, $queue);
    }
});

it('returns only the dead deliveries of the given message', function () {
    subscribeDeadConsumers();
    $first = brokerEnvelope();
    $second = brokerEnvelope();
    killDelivery('testing.failing_consumer', $first);
    killDelivery('testing.second_failing_consumer', $first);
    killDelivery('testing.spy_consumer', $second);

    $exitCode = Artisan::call('events:retry', ['id' => $first->id]);

    expect($exitCode)->toBe(0);
    awaitReadyMessages('testing.failing_consumer', 1);
    awaitReadyMessages('testing.second_failing_consumer', 1);

    expect(readyMessages('testing.spy_consumer'))->toBe(0)
        ->and(readyMessages('testing.failing_consumer.dead'))->toBe(0)
        ->and(readyMessages('testing.second_failing_consumer.dead'))->toBe(0)
        ->and(idsIn('testing.spy_consumer.dead'))->toBe([$second->id])
        ->and(idsIn('testing.failing_consumer'))->toBe([$first->id])
        ->and(idsIn('testing.second_failing_consumer'))->toBe([$first->id]);
});

it('refuses to return a message that is not dead', function () {
    subscribeDeadConsumers();
    killDelivery('testing.failing_consumer', brokerEnvelope());

    $exitCode = Artisan::call('events:retry', ['id' => unknownId()]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('não encontrada')
        ->and(readyMessages('testing.failing_consumer.dead'))->toBe(1)
        ->and(readyMessages('testing.failing_consumer'))->toBe(0);
});

it('keeps every dead delivery somewhere when the retry stops halfway', function () {
    subscribeDeadConsumers();
    $envelopes = [
        'testing.spy_consumer' => brokerEnvelope(),
        'testing.failing_consumer' => brokerEnvelope(),
        'testing.second_failing_consumer' => brokerEnvelope(),
    ];

    foreach ($envelopes as $consumer => $envelope) {
        killDelivery($consumer, $envelope);
    }

    // The publication of the second delivery fails.
    app()->instance(DeadDeliveries::class, new class(app(RabbitMqConnector::class), app(RabbitMqTopology::class)) extends DeadDeliveries
    {
        public int $returns = 0;

        protected function republish(AMQPChannel $channel, AMQPMessage $message, string $consumerQueue): void
        {
            if (++$this->returns === 2) {
                throw new RuntimeException('publisher down');
            }

            parent::republish($channel, $message, $consumerQueue);
        }
    });

    expect(Artisan::call('events:retry'))->toBe(1);

    foreach ($envelopes as $consumer => $envelope) {
        expect(array_merge(idsIn($consumer), idsIn("{$consumer}.dead")))->toContain($envelope->id);
    }
});

it('sends a returned delivery back to the dead queue after three more failures', function () {
    subscribeDeadConsumers();
    $failing = new FailingConsumer;
    app()->instance(FailingConsumer::class, $failing);
    $envelope = brokerEnvelope();
    killDelivery('testing.failing_consumer', $envelope);
    expect(Artisan::call('events:retry'))->toBe(0);

    expect(consumeUntilEmpty())->toBe(0);

    expect($failing->attempts)->toBe(3);
    awaitReadyMessages('testing.failing_consumer.dead', 1);
    expect(idsIn('testing.failing_consumer.dead'))->toBe([$envelope->id]);
});

it('refuses the dead delivery commands without the broker', function (string $command) {
    subscribeDeadConsumers();
    config(['rabbitmq.port' => 1]);

    $exitCode = Artisan::call($command);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('RabbitMQ');
})->with(['events:failed', 'events:retry']);
