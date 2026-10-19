<?php

use Illuminate\Console\Scheduling\Schedule;

it('configures horizon like the queue worker', function (string $environment) {
    $supervisors = config('horizon.environments')[$environment];

    expect($supervisors)->toHaveCount(1);

    $supervisor = array_values($supervisors)[0];

    expect($supervisor['connection'])->toBe('redis')
        ->and($supervisor['queue'])->toBe(['default'])
        ->and($supervisor['balance'])->toBeFalse()
        ->and($supervisor['minProcesses'])->toBe(1)
        ->and($supervisor['maxProcesses'])->toBe(1)
        ->and($supervisor['tries'])->toBe(3)
        ->and($supervisor['timeout'])->toBe(60)
        ->and($supervisor['sleep'])->toBe(1);
})->with(['local', 'production']);

it('opens horizon for a staff admin', function () {
    $this->actingAs(admin(), 'staff')->get('/horizon')->assertOk();
});

it('refuses horizon to anyone but a staff admin', function (Closure $requester, string $path, string $environment) {
    // Horizon alone would open the dashboard to everyone in the local environment.
    app()->detectEnvironment(fn () => $environment);
    $requester($this)->get($path)->assertForbidden();
})->with([
    'support' => [fn ($test) => $test->actingAs(support(), 'staff')],
    'customer' => [fn ($test) => $test->actingAs(customer(), 'customer')],
    'visitor' => [fn ($test) => $test],
])->with(['/horizon', '/horizon/api/stats'])->with(['local', 'production']);

it('schedules the horizon snapshot every five minutes', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'horizon:snapshot'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('*/5 * * * *');
});
