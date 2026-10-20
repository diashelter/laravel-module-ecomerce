<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;
use Modules\Messaging\Core\QueueEventTransport;
use Modules\Messaging\Core\RabbitMq\RabbitMqEventTransport;
use Modules\Messaging\Persistence\Models\OutboxMessage;
use Modules\Messaging\Tests\Fixtures\SpyTransport;

/** The rows of the flag in the Pennant table. */
function flagRows(): Collection
{
    return DB::table('features')->where('name', 'event-transport')->get();
}

/** Binds a spy for each transport the selector can hand an envelope to. */
function spyBothTransports(): array
{
    $redis = new SpyTransport;
    $rabbitmq = new SpyTransport;
    app()->instance(QueueEventTransport::class, $redis);
    app()->instance(RabbitMqEventTransport::class, $rabbitmq);

    return [$redis, $rabbitmq];
}

it('resolves the event transport to redis when nothing is stored', function () {
    expect(flagRows())->toHaveCount(0)
        ->and(Feature::globally()->value('event-transport'))->toBe('redis');
});

it('shows the current event transport', function () {
    $exitCode = Artisan::call('events:transport');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('redis');
});

it('switches the event transport to rabbitmq', function () {
    $exitCode = Artisan::call('events:transport', ['value' => 'rabbitmq', '--by' => 'ana']);
    $output = Artisan::output();

    Feature::flushCache();
    $rows = flagRows();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('redis')->toContain('rabbitmq')
        ->and($rows)->toHaveCount(1)
        ->and($rows->first()->scope)->toBe('__laravel_global')
        ->and($rows->first()->value)->toBe('"rabbitmq"')
        ->and(Feature::globally()->value('event-transport'))->toBe('rabbitmq');
});

it('refuses an unknown event transport', function (string $value) {
    $exitCode = Artisan::call('events:transport', ['value' => $value]);
    $output = Artisan::output();

    Feature::flushCache();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('redis')->toContain('rabbitmq')
        ->and(Feature::globally()->value('event-transport'))->toBe('redis');
})->with(['bogus', 'Redis', 'kafka']);

it('changes nothing when the event transport already has the given value', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00'));
    storeFlag('rabbitmq');
    $updatedAt = flagRows()->sole()->updated_at;
    $this->travel(1)->minute();
    Log::spy();

    $exitCode = Artisan::call('events:transport', ['value' => 'rabbitmq']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('já')
        ->and(flagRows()->sole()->updated_at)->toBe($updatedAt);
    Log::shouldNotHaveReceived('info');
});

it('logs who changed the event transport', function () {
    Log::spy();

    Artisan::call('events:transport', ['value' => 'rabbitmq', '--by' => 'ana']);

    Log::shouldHaveReceived('info')->once()->withArgs(
        fn (string $message, array $context) => $context === ['previous' => 'redis', 'current' => 'rabbitmq', 'changed_by' => 'ana'],
    );
});

it('changes the event transport with redis unreachable', function () {
    config([
        'cache.default' => 'redis',
        'database.redis.default.port' => 1,
        'database.redis.cache.port' => 1,
    ]);

    $changed = Artisan::call('events:transport', ['value' => 'rabbitmq']);
    Artisan::call('events:transport');

    expect($changed)->toBe(0)
        ->and(Artisan::output())->toContain('rabbitmq');
});

it('hands the envelope to the transport of the flag', function (string $value, int $receiver) {
    $spies = spyBothTransports();
    storeFlag($value);
    $message = OutboxMessage::factory()->create();

    expect(relayOnce())->toBe(0);

    expect($spies[$receiver]->received)->toHaveCount(1)
        ->and($spies[$receiver]->received[0]->id)->toBe($message->id)
        ->and($spies[1 - $receiver]->received)->toBe([]);
})->with([
    'redis' => ['redis', 0],
    'rabbitmq' => ['rabbitmq', 1],
]);

it('publishes through the new transport on the next relay cycle', function () {
    [$redis, $rabbitmq] = spyBothTransports();
    $first = OutboxMessage::factory()->create();

    expect(relayOnce())->toBe(0);

    // Changed behind Pennant's back, as `make event-transport` does from another process.
    DB::table('features')->updateOrInsert(
        ['name' => 'event-transport', 'scope' => '__laravel_global'],
        ['value' => '"rabbitmq"', 'created_at' => now(), 'updated_at' => now()],
    );
    $second = OutboxMessage::factory()->create();

    expect(relayOnce())->toBe(0);

    expect(array_map(fn ($envelope) => $envelope->id, $redis->received))->toBe([$first->id])
        ->and(array_map(fn ($envelope) => $envelope->id, $rabbitmq->received))->toBe([$second->id]);
});

it('logs each transport change once', function () {
    spyBothTransports();
    Log::spy();

    foreach (['redis', 'redis', 'rabbitmq', 'rabbitmq', 'redis'] as $value) {
        storeFlag($value);
        OutboxMessage::factory()->create();
        expect(relayOnce())->toBe(0);
    }

    $lines = [];
    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use (&$lines) {
        $lines[] = $context['transport'] ?? null;

        return true;
    });

    expect($lines)->toBe(['rabbitmq', 'redis']);
});
