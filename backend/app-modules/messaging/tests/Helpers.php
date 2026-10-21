<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;
use Modules\Messaging\Contracts\DomainEvent;
use Modules\Messaging\Core\EventDelivery;
use Modules\Messaging\Core\EventEnvelope;
use Modules\Messaging\Core\EventTransport;
use Modules\Messaging\Core\RabbitMq\RabbitMqConnector;
use Modules\Messaging\Core\RabbitMq\RabbitMqEventTransport;
use Modules\Messaging\Persistence\Models\OutboxMessage;
use Modules\Messaging\Tests\Fixtures\SpyConsumer;
use Modules\Messaging\Tests\Fixtures\SpyTransport;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * The outbox messages recorded under an event name, oldest first.
 *
 * @return Collection<int, OutboxMessage>
 */
function outboxMessages(string $name): Collection
{
    return OutboxMessage::query()->where('name', $name)->orderBy('occurred_at')->orderBy('id')->get();
}

/** One `php artisan outbox:relay --once`: hands every pending message to the transport. Returns the exit code. */
function relayOnce(): int
{
    return Artisan::call('outbox:relay', ['--once' => true]);
}

/**
 * Runs the relay until no message is pending. With the sync queue each cycle runs the consumers,
 * which may record the next events of the chain.
 */
function relayUntilIdle(): void
{
    for ($cycle = 1; OutboxMessage::query()->whereNull('published_at')->exists(); $cycle++) {
        expect($cycle)->toBeLessThanOrEqual(10, 'The outbox never went idle.')
            ->and(relayOnce())->toBe(0);
    }
}

/**
 * Binds a spy as the event transport the relay publishes through. It refuses the message ids in
 * $failing with RuntimeException('transport down').
 *
 * @param  list<string>  $failing
 */
function spyTransport(array $failing = [], ?Closure $onPublish = null): SpyTransport
{
    $spy = new SpyTransport($failing, $onPublish);
    app()->instance(EventTransport::class, $spy);

    return $spy;
}

/**
 * A call to the HTTP API of the RabbitMQ management plugin, with the credentials of the .env. The
 * tests read queues, arguments, bindings and counts from it and never write through it, except to
 * empty the vhost (resetBroker).
 *
 * @return array<mixed>
 */
function rabbitApi(string $path, string $method = 'get'): array
{
    $url = 'http://'.config('rabbitmq.host').':15672/api/'.ltrim($path, '/');

    try {
        $response = Http::withBasicAuth((string) config('rabbitmq.user'), (string) config('rabbitmq.password'))
            ->timeout(5)
            ->send(strtoupper($method), $url);
    } catch (Throwable $e) {
        throw new RuntimeException("RabbitMQ management API is unreachable at {$url}: {$e->getMessage()}", previous: $e);
    }

    if ($response->failed()) {
        throw new RuntimeException("RabbitMQ management API answered {$response->status()} to {$method} {$url}.");
    }

    return $response->json() ?? [];
}

/** The path segment of the vhost of this checkout. */
function rabbitVhostPath(): string
{
    return rawurlencode((string) config('rabbitmq.vhost'));
}

/**
 * Deletes every queue and every exchange the vhost holds beyond the broker's own (the default
 * exchange "" and the amq.* ones), so a test never depends on what another left behind.
 */
function resetBroker(): void
{
    // Without the broker every test of the folder fails here, saying so (RabbitMQ is unreachable at ...),
    // instead of failing later on a symptom: none is skipped.
    rabbitConnection()->close();

    $vhost = rabbitVhostPath();

    foreach (rabbitApi("queues/{$vhost}") as $queue) {
        rabbitApi("queues/{$vhost}/".rawurlencode($queue['name']), 'delete');
    }

    foreach (rabbitApi("exchanges/{$vhost}") as $exchange) {
        if ($exchange['name'] !== '' && ! str_starts_with($exchange['name'], 'amq.')) {
            rabbitApi("exchanges/{$vhost}/".rawurlencode($exchange['name']), 'delete');
        }
    }
}

/**
 * The names of the queues and of the exchanges the vhost holds beyond the broker's own.
 *
 * @return array{queues: list<string>, exchanges: list<string>}
 */
function brokerObjects(): array
{
    $vhost = rabbitVhostPath();

    return [
        'queues' => collect(rabbitApi("queues/{$vhost}"))->pluck('name')->sort()->values()->all(),
        'exchanges' => collect(rabbitApi("exchanges/{$vhost}"))->pluck('name')
            ->reject(fn (string $name) => $name === '' || str_starts_with($name, 'amq.'))
            ->sort()->values()->all(),
    ];
}

/** A direct AMQP connection to the vhost of this checkout, for a test that publishes or reads like a client. */
function rabbitConnection(): AMQPStreamConnection
{
    return app(RabbitMqConnector::class)->connect();
}

/** The messages ready in the queue, read from the broker itself (no statistics delay). */
function readyMessages(string $queue): int
{
    $connection = rabbitConnection();

    try {
        return $connection->channel()->queue_declare($queue, true)[1];
    } finally {
        $connection->close();
    }
}

/** Waits until the queue holds exactly $messages ready messages, read from the broker itself. */
function awaitReadyMessages(string $queue, int $messages, int $seconds = 10): void
{
    $deadline = microtime(true) + $seconds;

    while (($ready = readyMessages($queue)) !== $messages) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException("The queue {$queue} holds {$ready} ready messages, not {$messages}.");
        }

        usleep(100_000);
    }
}

/**
 * Polls the management API until the queue satisfies the condition. The counts of a quorum queue
 * follow the broker by up to a tick of the Raft system (about 5 seconds), so this is for the
 * counts only the API has (the unacknowledged ones); the ready ones are readyMessages(). Fails
 * after $seconds.
 *
 * @param  Closure(array<string, mixed>): bool  $until
 * @return array<string, mixed> the queue, as the API shows it
 */
function awaitQueue(string $queue, Closure $until, int $seconds = 20): array
{
    $deadline = microtime(true) + $seconds;
    $vhost = rabbitVhostPath();

    do {
        $state = rabbitApi("queues/{$vhost}/".rawurlencode($queue));

        if ($until($state)) {
            return $state;
        }

        usleep(100_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException("The queue {$queue} never reached the expected state: ".json_encode(array_intersect_key($state, array_flip(['messages_ready', 'messages_unacknowledged', 'consumers']))));
}

/** An envelope of the test event, to publish to the broker. */
function brokerEnvelope(array $overrides = []): EventEnvelope
{
    return new EventEnvelope(...array_merge([
        'id' => unknownId(),
        'name' => 'testing.sample_event',
        'version' => 1,
        'payload' => ['order_id' => unknownId(), 'quantity' => 3],
        'occurredAt' => CarbonImmutable::parse('2026-10-18 12:00:00'),
        'requestId' => 'trace-broker-0001',
    ], $overrides));
}

/** Publishes through the RabbitMQ transport of the application, which also declares the topology. */
function publishToBroker(EventEnvelope $envelope): void
{
    app(RabbitMqEventTransport::class)->publish($envelope);
}

/** Binds a spy instance of the consumer class, so a delivery resolves that same instance. */
function bindSpy(string $consumer = SpyConsumer::class): SpyConsumer
{
    $spy = new $consumer;
    app()->instance($consumer, $spy);

    return $spy;
}

/** Stores a value for the event-transport flag straight in the table, as another process would. */
function storeFlag(string $value): void
{
    DB::table('features')->updateOrInsert(
        ['name' => 'event-transport', 'scope' => '__laravel_global'],
        ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()],
    );
    Feature::flushCache();
}

/** `php artisan events:consume --stop-when-empty`: returns the exit code. */
function consumeUntilEmpty(): int
{
    return Artisan::call('events:consume', ['--stop-when-empty' => true]);
}

/** The envelope the relay would build for an event, with a fresh message id (override `id` to redeliver the same message). */
function envelopeOf(DomainEvent $event, array $overrides = []): EventEnvelope
{
    return new EventEnvelope(...array_merge([
        'id' => unknownId(),
        'name' => $event::eventName(),
        'version' => $event::eventVersion(),
        'payload' => $event->toPayload(),
        'occurredAt' => CarbonImmutable::now(),
        'requestId' => null,
    ], $overrides));
}

/** One delivery of the envelope to the consumer through the rules both transports share (the DeliverEvent job and events:consume call the same class). */
function deliverEnvelope(EventEnvelope $envelope, string $consumer): void
{
    app(EventDelivery::class)->deliver($envelope, $consumer);
}
