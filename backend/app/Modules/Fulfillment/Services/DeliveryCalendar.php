<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The delivery calendar rule: business days are Monday to Friday, holidays are not considered,
 * and the day is the one it is in São Paulo (the store's time), not in UTC.
 */
class DeliveryCalendar
{
    public const TIMEZONE = 'America/Sao_Paulo';

    /**
     * The day the delivery is expected: the payment day plus the business days. A payment made on
     * a weekend counts from the next business day (Saturday + 2 is the following Tuesday).
     */
    public function estimate(CarbonInterface $paidAt, int $businessDays): CarbonImmutable
    {
        $date = CarbonImmutable::instance($paidAt)->setTimezone(self::TIMEZONE)->startOfDay();

        for ($remaining = $businessDays; $remaining > 0;) {
            $date = $date->addDay();

            if ($date->isWeekday()) {
                $remaining--;
            }
        }

        return $date;
    }
}
