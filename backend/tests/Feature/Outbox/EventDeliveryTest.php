<?php

use App\Modules\Shared\Messaging\DeliverEvent;
use App\Modules\Shared\Messaging\EventEnvelope;
use App\Modules\Shared\Messaging\EventOutbox;
use App\Modules\Shared\Messaging\EventSubscriptions;
use App\Modules\Shared\Messaging\QueueEventTransport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Outbox\Fixtures\FailingConsumer;
use Tests\Feature\Outbox\Fixtures\SampleEvent;
use Tests\Feature\Outbox\Fixtures\SampleEventTwin;
use Tests\Feature\Outbox\Fixtures\SecondSpyConsumer;
use Tests\Feature\Outbox\Fixtures\SpyConsumer;
use Tests\Feature\Outbox\Fixtures\TwinConsumer;
use Tests\Feature\Outbox\Fixtures\UnmarkedConsumer;
use Tests\Feature\Outbox\Fixtures\WrongEventConsumer;

/** An envelope of the test event, as the relay would build it. */
function sampleEnvelope(array $overrides = []): EventEnvelope
{
    $values = array_merge([
        'id' => unknownId(),
        'name' => 'testing.sample_event',
        'version' => 1,
        'payload' => ['order_id' => unknownId(), 'quantity' => 3],
        'occurredAt' => CarbonImmutable::parse('2026-10-15 12:00:00'),
        'requestId' => 'trace-envelope-0001',
    ], $overrides);

    return new EventEnvelope(...$values);
}

/** Binds a spy instance of the consumer class, so the delivery resolves that same instance. */
function spyOn(string $consumer): SpyConsumer
{
    $spy = new $consumer;
    app()->instance($consumer, $spy);

    return $spy;
}

it('pushes one delivery per subscribed consumer with the whole envelope', function () {
    Queue::fake()->serializeAndRestore();
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SpyConsumer::class);
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SecondSpyConsumer::class);
    $envelope = sampleEnvelope();

    app(QueueEventTransport::class)->publish($envelope);

    Queue::assertPushed(DeliverEvent::class, 2);
    $jobs = Queue::pushed(DeliverEvent::class);

    expect($jobs->pluck('consumer')->sort()->values()->all())->toBe([SecondSpyConsumer::class, SpyConsumer::class]);

    foreach ($jobs as $job) {
        expect($job->envelope->id)->toBe($envelope->id)
            ->and($job->envelope->name)->toBe('testing.sample_event')
            ->and($job->envelope->version)->toBe(1)
            ->and($job->envelope->payload)->toBe($envelope->payload)
            ->and($job->envelope->occurredAt->equalTo($envelope->occurredAt))->toBeTrue()
            ->and($job->envelope->requestId)->toBe('trace-envelope-0001')
            ->and($job->connection)->toBeNull();
    }
});

it('publishes an envelope with no subscribed consumer without pushing a job', function () {
    Queue::fake();
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SpyConsumer::class);

    app(QueueEventTransport::class)->publish(sampleEnvelope(['name' => 'testing.unheard_event']));

    Queue::assertNothingPushed();
});

it('rebuilds the event and calls the consumer once', function () {
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SpyConsumer::class);
    $spy = spyOn(SpyConsumer::class);
    $envelope = sampleEnvelope();

    Bus::dispatch(new DeliverEvent($envelope, SpyConsumer::class));

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['event'])->toBeInstanceOf(SampleEvent::class)
        ->and($spy->calls[0]['event']->payload)->toBe($envelope->payload);
});

it('restores the envelope request id while the consumer runs', function () {
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SpyConsumer::class);
    $spy = spyOn(SpyConsumer::class);
    expect(Context::has('request_id'))->toBeFalse();

    Bus::dispatch(new DeliverEvent(sampleEnvelope(['requestId' => 'trace-delivery-0001']), SpyConsumer::class));

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['request_id'])->toBe('trace-delivery-0001');
});

it('clears a stale request id when the envelope has none', function () {
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SpyConsumer::class);
    $spy = spyOn(SpyConsumer::class);
    Context::add('request_id', 'stale-request-0001');

    Bus::dispatch(new DeliverEvent(sampleEnvelope(['requestId' => null]), SpyConsumer::class));

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['request_id'])->toBeNull();
});

it('refuses an envelope version the event does not declare', function () {
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SpyConsumer::class);
    $spy = spyOn(SpyConsumer::class);

    expect(fn () => Bus::dispatch(new DeliverEvent(sampleEnvelope(['version' => 2]), SpyConsumer::class)))
        ->toThrow(function (UnexpectedValueException $e) {
            expect($e->getMessage())->toContain('testing.sample_event')->toContain('2');
        });

    expect($spy->calls)->toBeEmpty();
});

it('retries only the failing consumer three times and fails it alone', function () {
    config(['queue.default' => 'database']);
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, FailingConsumer::class);
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SpyConsumer::class);
    $failing = new FailingConsumer;
    app()->instance(FailingConsumer::class, $failing);
    $counting = spyOn(SpyConsumer::class);

    app(QueueEventTransport::class)->publish(sampleEnvelope());
    expect(DB::table('jobs')->count())->toBe(2);

    // The worker stops past --memory (128 MB by default), which a long test run can already use.
    Artisan::call('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--memory' => 4096]);

    $failed = DB::table('failed_jobs')->get();
    $failedJob = unserialize(json_decode($failed->sole()->payload, true)['data']['command']);

    expect((new DeliverEvent(sampleEnvelope(), SpyConsumer::class))->tries)->toBe(3)
        ->and($failing->attempts)->toBe(3)
        ->and($counting->calls)->toHaveCount(1)
        ->and($failed)->toHaveCount(1)
        ->and($failedJob)->toBeInstanceOf(DeliverEvent::class)
        ->and($failedJob->consumer)->toBe(FailingConsumer::class)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('refuses a consumer that does not match the subscribed event', function (string $consumer) {
    $subscriptions = app(EventSubscriptions::class);

    expect(fn () => $subscriptions->subscribe(SampleEvent::class, $consumer))->toThrow(LogicException::class);

    expect($subscriptions->consumersOf('testing.sample_event'))->toBe([]);
})->with([
    'handle() typed by another event' => [WrongEventConsumer::class],
    'not an EventConsumer' => [UnmarkedConsumer::class],
]);

it('refuses two event classes under the same name', function () {
    $subscriptions = app(EventSubscriptions::class);
    $subscriptions->subscribe(SampleEvent::class, SpyConsumer::class);

    expect(fn () => $subscriptions->subscribe(SampleEventTwin::class, TwinConsumer::class))->toThrow(LogicException::class);

    expect($subscriptions->consumersOf('testing.sample_event'))->toBe([SpyConsumer::class])
        ->and($subscriptions->eventOf('testing.sample_event'))->toBe(SampleEvent::class);
});

it('delivers an envelope whose message was already pruned', function () {
    Queue::fake();
    app(EventSubscriptions::class)->subscribe(SampleEvent::class, SpyConsumer::class);
    $spy = spyOn(SpyConsumer::class);
    $event = new SampleEvent(['order_id' => unknownId()]);
    DB::transaction(fn () => app(EventOutbox::class)->record($event));

    expect(relayOnce())->toBe(0);
    DB::table('outbox_messages')->delete();
    $job = Queue::pushed(DeliverEvent::class)->sole();
    app()->call([$job, 'handle']);

    expect(DB::table('outbox_messages')->count())->toBe(0)
        ->and($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['event']->payload)->toBe($event->payload);
});

it('registers no laravel listener for the module events', function () {
    $events = array_map(
        fn (string $file) => 'App\\Modules\\'.basename(dirname($file, 2)).'\\Events\\'.basename($file, '.php'),
        glob(app_path('Modules/*/Events/*.php')),
    );

    expect($events)->toHaveCount(5);

    foreach ($events as $event) {
        expect(class_exists($event))->toBeTrue()
            ->and(Event::hasListeners($event))->toBeFalse("{$event} has a Laravel listener");
    }
});
