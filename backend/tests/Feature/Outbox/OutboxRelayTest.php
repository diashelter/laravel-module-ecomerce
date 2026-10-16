<?php

use App\Modules\Shared\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

const RESTART_SIGNAL = 'illuminate:queue:restart';

function pendingMessage(array $attributes = []): OutboxMessage
{
    return OutboxMessage::factory()->create($attributes);
}

it('publishes every pending message in order with once', function () {
    $now = CarbonImmutable::parse('2026-10-15 12:00:00');
    $this->travelTo($now);
    [$lowId, $highId] = collect([Str::uuid7()->toString(), Str::uuid7()->toString()])->sort()->values()->all();
    // Created out of order on purpose: the order comes from occurred_at, then id.
    $latest = pendingMessage(['occurred_at' => $now->subMinutes(15)]);
    $tiedHigh = pendingMessage(['id' => $highId, 'occurred_at' => $now->subMinutes(30)]);
    $oldest = pendingMessage(['occurred_at' => $now->subHour()]);
    $tiedLow = pendingMessage(['id' => $lowId, 'occurred_at' => $now->subMinutes(30)]);
    $published = pendingMessage(['occurred_at' => $now->subHours(2), 'published_at' => $now->subMinutes(110)]);
    $transport = spyTransport();

    expect(relayOnce())->toBe(0);

    expect(array_map(fn ($envelope) => $envelope->id, $transport->received))
        ->toBe([$oldest->id, $tiedLow->id, $tiedHigh->id, $latest->id]);

    foreach ([$oldest, $tiedLow, $tiedHigh, $latest] as $message) {
        expect($message->fresh()->published_at->equalTo($now))->toBeTrue();
    }

    expect($published->fresh()->published_at->equalTo($now->subMinutes(110)))->toBeTrue();
});

it('stops at the first transport failure and keeps the rest pending', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    $first = pendingMessage(['occurred_at' => now()->subMinutes(3)]);
    $second = pendingMessage(['occurred_at' => now()->subMinutes(2)]);
    $third = pendingMessage(['occurred_at' => now()->subMinute()]);
    $transport = spyTransport(failing: [$second->id]);
    Log::spy();

    relayOnce();

    expect($first->fresh()->published_at)->not->toBeNull()
        ->and($second->fresh()->published_at)->toBeNull()
        ->and($third->fresh()->published_at)->toBeNull()
        ->and(array_map(fn ($envelope) => $envelope->id, $transport->received))->toBe([$first->id, $second->id]);

    Log::shouldHaveReceived('warning')->once()->with('Outbox message could not be published.', [
        'message_id' => $second->id,
        'name' => 'testing.sample_event',
        'error' => 'transport down',
    ]);
});

it('exits with 1 when the transport fails with once', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    pendingMessage(['occurred_at' => now()->subMinutes(3)]);
    $second = pendingMessage(['occurred_at' => now()->subMinutes(2)]);
    pendingMessage(['occurred_at' => now()->subMinute()]);
    spyTransport(failing: [$second->id]);

    expect(relayOnce())->toBe(1);
});

it('claims the pending messages with for update skip locked', function () {
    pendingMessage();
    spyTransport();

    $queries = queriesDuring(fn () => relayOnce());
    $claims = array_values(array_filter($queries, fn (string $sql) => str_starts_with($sql, 'select') && str_contains($sql, 'from "outbox_messages"')));

    expect($claims)->toHaveCount(1)
        ->and($claims[0])->toContain('where "published_at" is null')
        ->and($claims[0])->toContain('order by "occurred_at" asc, "id" asc')
        ->and($claims[0])->toContain('limit 100')
        ->and($claims[0])->toContain('for update skip locked');
});

it('waits 500 ms after a cycle with no pending message', function () {
    spyTransport();
    Sleep::fake();
    Sleep::whenFakingSleep(fn () => Cache::forever(RESTART_SIGNAL, 'restart-after-idle'));

    expect(Artisan::call('outbox:relay'))->toBe(0);

    Sleep::assertSequence([Sleep::for(500)->milliseconds()]);
});

it('waits 5 seconds after a transport failure', function () {
    $message = pendingMessage();
    $transport = spyTransport(failing: [$message->id]);
    Sleep::fake();
    Sleep::whenFakingSleep(fn () => Cache::forever(RESTART_SIGNAL, 'restart-after-failure'));

    expect(Artisan::call('outbox:relay'))->toBe(0);

    Sleep::assertSequence([Sleep::for(5)->seconds()]);
    expect($transport->accepted)->toBeEmpty()
        ->and($message->fresh()->published_at)->toBeNull();
});

it('prunes published messages older than 7 days and keeps pending ones', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    $expired = pendingMessage(['occurred_at' => now()->subDays(8), 'published_at' => now()->subDays(7)->subMinute()]);
    $recent = pendingMessage(['occurred_at' => now()->subDays(7), 'published_at' => now()->subDays(6)->subHours(23)]);
    $oldPending = pendingMessage(['occurred_at' => now()->subDays(30)]);
    // The old pending message stays pending through the run: the transport refuses it.
    spyTransport(failing: [$oldPending->id]);

    relayOnce();

    expect(OutboxMessage::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(OutboxMessage::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and($oldPending->fresh()->published_at)->toBeNull();
});

it('prunes again only after an hour', function () {
    $start = CarbonImmutable::parse('2026-10-15 12:00:00');
    $this->travelTo($start);
    spyTransport();
    $prunes = [];
    DB::listen(function ($query) use (&$prunes) {
        if (str_starts_with($query->sql, 'delete from "outbox_messages"')) {
            $prunes[] = now()->toImmutable();
        }
    });
    // Each wait moves the clock one minute; the restart signal changes once 61 minutes passed.
    Sleep::fake();
    Sleep::whenFakingSleep(function () use ($start) {
        $this->travel(1)->minutes();

        if (now()->greaterThanOrEqualTo($start->addMinutes(61))) {
            Cache::forever(RESTART_SIGNAL, 'restart-after-an-hour');
        }
    });

    expect(Artisan::call('outbox:relay'))->toBe(0);

    expect(array_map(fn (CarbonImmutable $at) => $at->toDateTimeString(), $prunes))
        ->toBe([$start->toDateTimeString(), $start->addMinutes(60)->toDateTimeString()]);
});

it('exits after the cycle when the queue restart signal changes', function () {
    Sleep::fake();
    $message = pendingMessage();
    $transport = spyTransport(onPublish: fn () => Cache::forever(RESTART_SIGNAL, 'restart-while-publishing'));

    expect(Artisan::call('outbox:relay'))->toBe(0);

    expect($transport->received)->toHaveCount(1)
        ->and($message->fresh()->published_at)->not->toBeNull();
    Sleep::assertSleptTimes(0);

    // The signal written by `php artisan queue:restart` during a wait stops it too.
    Sleep::fake();
    Sleep::whenFakingSleep(fn () => Artisan::call('queue:restart'));

    expect(Artisan::call('outbox:relay'))->toBe(0);

    Sleep::assertSleptTimes(1);
});

it('prints one warning line when the transport refuses a message', function () {
    $message = pendingMessage();
    spyTransport(failing: [$message->id]);

    relayOnce();

    expect(Artisan::output())->toContain('The transport refused an outbox message');
});

it('keeps relaying while the cache with the restart signal is unreachable', function () {
    // Redis down: the cache throws, and the relay keeps publishing instead of crashing.
    $reads = 0;
    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('get')->with(RESTART_SIGNAL)->andReturnUsing(function () use (&$reads) {
        $reads++;

        if ($reads <= 2) {
            throw new RuntimeException('cache down');
        }

        return $reads === 3 ? 'signal-once-back' : 'signal-after-restart';
    });
    app()->instance(CacheRepository::class, $cache);
    $message = pendingMessage();
    $transport = spyTransport();
    Sleep::fake();

    expect(Artisan::call('outbox:relay'))->toBe(0);

    // Published while the cache was down; the first signal read back is adopted, the next one stops it.
    expect($transport->accepted)->toHaveCount(1)
        ->and($message->fresh()->published_at)->not->toBeNull()
        ->and($reads)->toBe(4);
    Sleep::assertSleptTimes(1);
});
