<?php

use App\Modules\Fulfillment\Services\DeliveryCalendar;
use Carbon\CarbonImmutable;

it('counts the delivery estimate in business days in Sao Paulo time', function (string $paidAt, string $timezone, int $businessDays, string $expected) {
    $estimate = (new DeliveryCalendar)->estimate(CarbonImmutable::parse($paidAt, $timezone), $businessDays);

    expect($estimate->toDateString())->toBe($expected)
        ->and($estimate->timezoneName)->toBe('America/Sao_Paulo');
})->with([
    'Wednesday plus 2' => ['2026-10-07 10:00', 'America/Sao_Paulo', 2, '2026-10-09'],
    'Friday plus 2' => ['2026-10-09 10:00', 'America/Sao_Paulo', 2, '2026-10-13'],
    'Saturday plus 2' => ['2026-10-10 10:00', 'America/Sao_Paulo', 2, '2026-10-13'],
    'Wednesday 23:30 in Sao Paulo, already Thursday in UTC' => ['2026-10-08 02:30', 'UTC', 2, '2026-10-09'],
    'Wednesday plus 10' => ['2026-10-07 10:00', 'America/Sao_Paulo', 10, '2026-10-21'],
]);
