<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Pennant\Feature;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Customers\Persistence\Models\CustomerAddress;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Identity\Persistence\Models\User;
use Modules\Inventory\Persistence\Models\Stock;
use Modules\Messaging\Core\EventEnvelope;
use Modules\Messaging\Core\EventTransport;
use Modules\Messaging\Core\RabbitMq\RabbitMqConnector;
use Modules\Messaging\Core\RabbitMq\RabbitMqEventTransport;
use Modules\Messaging\Persistence\Models\OutboxMessage;
use Modules\Ordering\Core\Services\OrderCodeGenerator;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Core\DTOs\ChargeRequest;
use Modules\Payment\Core\DTOs\ChargeResult;
use Modules\Payment\Gateways\FakePaymentGateway;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Tests\Feature\Outbox\Fixtures\SpyConsumer;
use Tests\Feature\Outbox\Fixtures\SpyTransport;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Every test that uses the broker starts on a vhost with nothing declared in it.
pest()->beforeEach(fn () => resetBroker())->in('Feature/Messaging');

// Unit tests boot the application (helpers, config) but never touch the database.
pest()->extend(TestCase::class)->in('Unit');

function customer(array $attributes = []): CustomerAccount
{
    return CustomerAccount::factory()->create($attributes);
}

/** A saved address of the customer's address book (SP, unless an override says otherwise). */
function addressOf(CustomerAccount $account, array $attributes = []): CustomerAddress
{
    return CustomerAddress::factory()->create(['customer_id' => $account->id, ...$attributes]);
}

/** Valid body for `POST|PUT /api/account/addresses`; pass overrides to change fields. */
function addressPayload(array $overrides = []): array
{
    return array_merge([
        'recipient_name' => 'Ana Souza',
        'postal_code' => '01310-100',
        'street' => 'Avenida Paulista',
        'number' => '1000',
        'complement' => 'Apto 12',
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
    ], $overrides);
}

/** A canonical UUID v7 that is in no table: what the tests send as an unknown id. */
function unknownId(): string
{
    return Str::uuid7()->toString();
}

/** A complete row for `orders`, written straight to the table with no model in between. */
function orderRow(string $customerId, array $overrides = []): array
{
    return array_merge([
        'id' => unknownId(),
        'code' => (new OrderCodeGenerator)->generate(),
        'customer_id' => $customerId,
        'total_cents' => 0,
        'shipping_cents' => 0,
        'delivery_business_days' => 2,
        'estimated_delivery_on' => null,
        'delivery_recipient_name' => 'Ana Souza',
        'delivery_postal_code' => '01310100',
        'delivery_street' => 'Avenida Paulista',
        'delivery_number' => '1000',
        'delivery_complement' => null,
        'delivery_district' => 'Bela Vista',
        'delivery_city' => 'São Paulo',
        'delivery_state' => 'SP',
        'status' => 'placed',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

/**
 * Replaces the order code draw with a fixed sequence, so a test decides which codes collide. The
 * returned generator counts the draws; asking for more codes than the sequence holds is an error.
 *
 * @param  list<string>  $codes
 */
function forceOrderCodes(array $codes): OrderCodeGenerator
{
    $generator = new class($codes) extends OrderCodeGenerator
    {
        public int $draws = 0;

        /** @param  list<string>  $codes */
        public function __construct(private array $codes) {}

        public function generate(): string
        {
            return $this->codes[$this->draws++] ?? throw new LogicException('The forced order codes ran out.');
        }
    };

    app()->instance(OrderCodeGenerator::class, $generator);

    return $generator;
}

/**
 * The SQL of every query the closure runs, in order. Sign in and create the data before calling it,
 * so only the request itself is counted.
 *
 * @return list<string>
 */
function queriesDuring(Closure $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();

    return array_column(DB::getQueryLog(), 'query');
}

function admin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}

function support(array $attributes = []): User
{
    return User::factory()->support()->create($attributes);
}

function productWithStock(int $quantity, array $attributes = []): Product
{
    return Product::factory()->withStock($quantity)->create($attributes);
}

/** The stock row of a product, read from `stocks` by its `product_id` (the product has no relation to it). */
function stockOf(Product $product): Stock
{
    return Stock::query()->where('product_id', $product->id)->firstOrFail();
}

/** Valid admin product payload (price in cents); pass overrides to change fields. */
function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Monitor 27',
        'price_cents' => 129990,
        'description' => 'Monitor IPS',
        'image_url' => null,
        'status' => 'active',
        'category_ids' => Category::factory()->count(2)->create()->pluck('id')->all(),
        'stock_quantity' => 7,
    ], $overrides);
}

/**
 * Asserts the standard API error body. The request_id must match the X-Request-ID header.
 *
 * @param  array<string, list<string>>  $errors
 */
function assertApiError(TestResponse $response, string $code, string $message, array $errors = []): TestResponse
{
    return $response->assertExactJson([
        'code' => $code,
        'message' => $message,
        'errors' => $errors,
        'request_id' => $response->headers->get('X-Request-ID'),
    ]);
}

/**
 * Binds a PaymentGateway double that records every charge it receives. It answers like the
 * fake gateway unless $answer (which receives the ChargeRequest) decides the result.
 */
function spyPaymentGateway(?Closure $answer = null): object
{
    $spy = new class($answer) implements PaymentGateway
    {
        /** @var list<ChargeRequest> */
        public array $charges = [];

        public function __construct(private readonly ?Closure $answer) {}

        public function charge(ChargeRequest $request): ChargeResult
        {
            $this->charges[] = $request;

            return $this->answer !== null
                ? ($this->answer)($request)
                : (new FakePaymentGateway)->charge($request);
        }
    };

    app()->instance(PaymentGateway::class, $spy);

    return $spy;
}

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
