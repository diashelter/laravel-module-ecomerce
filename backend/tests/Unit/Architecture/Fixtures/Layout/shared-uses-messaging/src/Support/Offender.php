<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Fixtures\Layout\SharedUsesMessaging;

use Modules\Messaging\Contracts\EventOutbox;

/** Deliberate violation: Shared names the Messaging module. Never loaded. */
final class Offender
{
    public function make(): string
    {
        return EventOutbox::class;
    }
}
