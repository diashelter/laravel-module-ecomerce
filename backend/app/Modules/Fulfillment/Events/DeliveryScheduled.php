<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Events;

use App\Modules\Fulfillment\Services\DeliveryCalendar;
use App\Modules\Shared\Messaging\DomainEvent;
use Carbon\CarbonImmutable;

/**
 * Fulfillment: the delivery of a paid order was scheduled for an estimated day. Ordering reacts by
 * recording the date on the order. Fulfillment never writes to the order itself.
 *
 * The payload carries the calendar day in São Paulo (Y-m-d), the day the DeliveryCalendar works
 * out, and reads it back as the start of that day there.
 */
class DeliveryScheduled implements DomainEvent
{
    public function __construct(public string $orderId, public CarbonImmutable $estimatedDeliveryOn) {}

    public static function eventName(): string
    {
        return 'fulfillment.delivery_scheduled';
    }

    public static function eventVersion(): int
    {
        return 1;
    }

    public function toPayload(): array
    {
        return [
            'order_id' => $this->orderId,
            'estimated_delivery_on' => $this->estimatedDeliveryOn->setTimezone(DeliveryCalendar::TIMEZONE)->format('Y-m-d'),
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new static(
            $payload['order_id'],
            CarbonImmutable::createFromFormat('Y-m-d', $payload['estimated_delivery_on'], DeliveryCalendar::TIMEZONE)->startOfDay(),
        );
    }
}
